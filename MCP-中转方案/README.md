这里我们以阿里云百炼平台的MCP server为例，有两种方案可选：<br>
1.便捷型中转；<br>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;我们通过部署MCP服务到百炼平台的通义千问上，第三方智能体通过API接口调用百炼平台，相当于是智能体套智能体。这个方案最便捷最简单，但是由于通义千问的调用是按照token数计费的，因此经济性较差。<br>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;这种方法适用于无法更换智能化平台的特殊情况，实现方法只需要一个文件，PHP方案，具体代码可见api.php文件。<br>
2.经济型中转；<br>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;我们搭建一个异步python程序，实现对MCP工具包的直接转发，这相对于上一个方案，它更加经济，但技术门槛较高，具体代码正在调试中，完成后会更新在本文件夹内，相信我，这不会太久。
