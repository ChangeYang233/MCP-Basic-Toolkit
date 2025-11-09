<?php
/**
 * 阿里云DashScope应用接口服务 - 问答交互处理（支持轮询机制）
 * 核心功能：
 * 1. 接收用户问题（question），生成问题ID（questionId）并异步调用大模型处理
 * 2. 客户端通过questionId轮询获取结果（answer），通过verify标记判断处理状态
 * 3. 大模型处理完成后，verify返回特定标签标识结果有效性
 */

// 设置响应头：指定JSON格式及跨域支持
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 处理预检请求（OPTIONS方法）：直接返回成功
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 配置参数：阿里云DashScope API密钥和应用ID
// 注意：生产环境需通过环境变量或加密配置文件加载，禁止硬编码密钥
const API_KEY = "阿里云API密钥";
const APPLICATION_ID = "阿里云应用ID";

// 读取请求数据（JSON格式）
$requestData = json_decode(file_get_contents('php://input'), true);

// 提取核心业务参数
$questionId = trim($requestData['questionId'] ?? ''); // questionId：问题唯一标识，用于关联轮询请求与结果
$question = trim($requestData['question'] ?? '');     // question：用户提交的问题内容

/**
 * 分支1：处理轮询请求（携带questionId）
 * 客户端通过问题ID（questionId）查询大模型处理结果，返回答案（answer）及校验标记（verify）
 */
if (!empty($questionId)) {
    // 从本地存储获取该问题的处理数据
    $questionData = getQuestionData($questionId);
    
    // 问题记录不存在或已过期：返回错误提示
    if (!$questionData) {
        echo json_encode([
            'questionId' => $questionId,  // 回传问题ID，便于客户端匹配请求
            'answer' => '问题记录不存在或已过期', // answer：返回错误信息（无有效答案时）
            'verify' => ''                // verify：空值表示无效状态
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 判断大模型是否处理完成：通过verify标记的特定标签识别
    $isCompleted = strpos($questionData['result'], '<!--COMPLETE-->') !== false;
    
    // 返回当前处理结果
    echo json_encode([
        'questionId' => $questionId,
        'answer' => $questionData['result'], // answer：大模型返回的答案内容（含中间态或最终结果）
        'verify' => $isCompleted ? '<!--COMPLETE-->' : '' // verify：完成标签/空值（标识处理状态）
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 分支2：处理新问题（无questionId）
 * 验证参数完整性，创建问题ID（questionId）后异步调用大模型，立即返回临时响应
 */
if (empty($question)) {
    echo json_encode([
        'questionId' => '',             // 无有效问题ID
        'answer' => '错误: 缺少问题内容', // answer：参数错误提示
        'verify' => ''                  // verify：空值表示无效状态
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 创建新问题ID（前缀+唯一ID，确保questionId唯一性）
$newQuestionId = 'qid_' . uniqid();
// 初始化问题数据（状态为处理中，等待大模型返回结果）
saveQuestionData($newQuestionId, [
    'status' => 'processing',  // 处理状态：processing(处理中)/completed(完成)
    'result' => '',            // 存储大模型返回的答案（answer的原始数据）
    'created_at' => time()     // 创建时间戳（用于后续过期清理）
]);

// 立即返回问题ID（questionId），告知客户端进入轮询等待
echo json_encode([
    'questionId' => $newQuestionId,   // 新创建的问题ID，供客户端轮询使用
    'answer' => '正在处理，请稍候...', // answer：临时提示信息（非最终答案）
    'verify' => ''                    // verify：空值表示处理未完成
], JSON_UNESCAPED_UNICODE);

// 结束客户端响应（不阻塞后续处理），提升前端体验
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// 异步调用大模型处理问题（独立于客户端响应的后台逻辑）
processQuestion($newQuestionId, $question, API_KEY, APPLICATION_ID);

/**
 * 异步处理用户问题：调用大模型API，获取答案后更新问题数据
 * @param string $questionId 问题ID（关联轮询请求）
 * @param string $questionContent 用户问题内容
 * @param string $apiKey 阿里云API密钥
 * @param string $appId 阿里云应用ID
 */
function processQuestion(string $questionId, string $questionContent, string $apiKey, string $appId) {
    // 配置：忽略客户端断开连接的影响，设置超时时间（2分钟）
    ignore_user_abort(true);
    set_time_limit(120);

    // 阿里云DashScope API请求地址
    $apiUrl = "https://dashscope.aliyuncs.com/api/v1/apps/{$appId}/completion";

    // 系统提示：约束大模型输出规则（业务场景定制）
    $systemPrompt = "请使用火车票MCP，不要臆想数据，请严格根据MCP返回内容回答问题。";
    // 最终提交给大模型的提示文本（系统规则+用户问题）
    $finalPrompt = "{$systemPrompt}\n\n用户问题：{$questionContent}";

    // 构造API请求参数
    $requestParams = [
        "input" => ["prompt" => $finalPrompt],
        "parameters" => new stdClass()  // 空参数对象（可根据API文档扩展配置）
    ];

    // 发起CURL请求调用大模型API
    $curlHandle = curl_init($apiUrl);
    curl_setopt_array($curlHandle, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,  // 要求返回响应内容
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Authorization: Bearer {$apiKey}"  // 阿里云API认证格式（Bearer Token）
        ],
        CURLOPT_POSTFIELDS => json_encode($requestParams, JSON_UNESCAPED_UNICODE)
    ]);
    $apiResponse = curl_exec($curlHandle);
    curl_close($curlHandle);

    // 处理API响应，生成最终答案（answer的内容）
    $answerContent = "服务暂时不可用，请稍后重试";  // 默认错误提示
    if ($apiResponse) {
        $responseJson = json_decode($apiResponse, true);
        $rawAnswer = $responseJson['output']['text'] ?? '';  // 大模型原始输出

        // 兼容API返回的字符串或数组格式
        if (is_array($rawAnswer)) {
            $answerContent = implode("\n", $rawAnswer);
        } else {
            $answerContent = (string)$rawAnswer;
        }

        // 清理输出内容，设置默认提示（如果结果为空）
        $answerContent = trim($answerContent);
        if ($answerContent === '') {
            $answerContent = '抱歉，我无法处理这个问题。';
        }
    }

    // 追加完成标记（用于verify参数，标识大模型处理完成）
    $answerContent .= '<!--COMPLETE-->';

    // 更新问题数据：保存最终答案（供轮询时返回answer）
    saveQuestionData($questionId, [
        'status' => 'completed',
        'result' => $answerContent,  // 存储完整答案（含verify标记）
        'created_at' => time()
    ]);
}

/**
 * 从本地存储获取问题处理数据
 * @param string $questionId 问题ID
 * @return array|null 问题数据（含答案原始内容，null表示记录不存在）
 */
function getQuestionData(string $questionId): ?array {
    $storagePath = sys_get_temp_dir() . "/question_{$questionId}.json";
    return file_exists($storagePath) 
        ? json_decode(file_get_contents($storagePath), true) 
        : null;
}

/**
 * 保存问题处理数据到本地存储
 * @param string $questionId 问题ID
 * @param array $data 问题数据（含状态、答案内容、时间戳）
 */
function saveQuestionData(string $questionId, array $data): void {
    $storagePath = sys_get_temp_dir() . "/question_{$questionId}.json";
    file_put_contents($storagePath, json_encode($data, JSON_UNESCAPED_UNICODE));
}
?>
