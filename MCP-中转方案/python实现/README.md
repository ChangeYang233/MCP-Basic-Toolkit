
# SSE Proxy Server

A lightweight proxy server that forwards POST requests to a backend
Server-Sent Events (SSE) service and streams the SSE response back to the client.

This project is designed for scenarios where a frontend application cannot
directly access an SSE API due to authentication restrictions, CORS issues,
or cross-domain limitations.  
By using this proxy, the frontend can access SSE streams through a simple,
unsecured local HTTP endpoint.

---

## 🚀 Features

- ✔ Forward HTTP POST requests to any SSE-compatible backend service  
- ✔ Stream SSE events to clients in real time  
- ✔ Full CORS support — works in browser environments  
- ✔ Production-ready logging with optional debug mode  
- ✔ Keeps sensitive data out of the codebase (API keys via environment variables)  
- ✔ Supports multithreaded request handling  

---

## 📦 Installation

Clone the repository:

```bash
git clone https://github.com/ChangeYang233/MCP-Basic-Toolkit.git
cd MCP-Basic-Toolkit/MCP-中转方案/python实现
````

Install dependencies:

```bash
pip install -r requirements.txt
```

---

## ⚙️ Configuration

Before running the server, configure environment variables:

```bash
export PROXY_TARGET_ENDPOINT="https://example.com/your-sse-endpoint"
export PROXY_API_KEY="sk-your-api-key"
```

Or create a `.env` file (optional):

```
PROXY_TARGET_ENDPOINT=https://example.com/sse
PROXY_API_KEY=sk-xxxxxx
```

---

## ▶️ Running the Server

Default port: **8000**

```bash
python server.py
```

Run on a custom port:

```bash
python server.py 9000
```

Server will start at:

```
http://localhost:8000
```

---

## 📡 Example Request

```bash
curl -X POST http://localhost:8000 \
     -H "Content-Type: application/json" \
     -d '{"prompt": "hello"}'
```

---

## 🧩 Project Structure

```
├── server.py        # Main proxy server
├── requirements.txt # Dependencies
├── README.md        # Documentation
└── LICENSE          # Optional
```

---

## 🛠 Environment Variable Table

| Variable Name           | Required | Description                                                   | Example Value                 |
| ----------------------- | -------- | ------------------------------------------------------------- | ----------------------------- |
| `PROXY_TARGET_ENDPOINT` | Yes      | The backend SSE API endpoint that receives forwarded requests | `https://api.example.com/sse` |
| `PROXY_API_KEY`         | Yes      | API key or token for authenticating with the SSE backend      | `sk-xxxx`                     |
| `PROXY_PORT` (optional) | No       | Port to run proxy server on (default: 8000)                   | `9000`                        |

---

## 🔒 Security Notes

* Never hardcode API keys — always use environment variables.
* This proxy is intended for trusted environments (backend or controlled server).
* If exposing publicly, consider adding:

  * Authentication
  * Rate limiting
  * IP allowlist

---

## 📄 License

MIT License — free for commercial and private use.

---

## 🤝 Contributing

PRs are welcome!
Feel free to open issues for bugs or feature requests.

---

## ⭐ Star This Project

If you think this project is useful, please consider giving it a ⭐ on GitHub!
