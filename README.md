# Telegram Test 專案 API 說明

本專案包含 **Server (8081)** 與 **Client (8082)** 兩個服務。  
Server 負責產生訊息並送到 Client 的 Webhook；Client 負責存資料、檢查是否觸發通知，以及查詢歷史紀錄。  
另外 Client 也提供「插隊測試」的功能。


---
##  啟動方式


```bash
docker-compose up -d --build
```
##  Server 端 (http://localhost:8081)

| Method | Path | 功能 | 範例 |
|--------|------|------|------|
| GET | `/setWebhook?url=...` | 設定 webhook URL | `http://localhost:8081/setWebhook?url=http://client/webhook` |
| GET | `/setWebhook?url=` | 清空 webhook（停止發送） | |
| GET | `/setTesting?test=true` | 開啟測試模式（固定 7 秒 + 相位差） | |
| GET | `/setTesting?test=false` | 關閉測試模式（隨機 1–15 秒） | |
| GET | `/health` | 健康檢查 | `http://localhost:8081/health` |

---

##  Client 端 (http://localhost:8082)

| Method | Path | 功能 | 範例 |
|--------|------|------|------|
| POST | `/webhook` | Server 呼叫的入口（接收訊息） | `curl -X POST http://localhost:8082/webhook -H "Content-Type: application/json" -d '{"env":"A1","text":"Something is wrong"}'` |
| GET | `/requests` | 查詢最近 1000 筆請求（HTML 表格） | `http://localhost:8082/requests` |
| GET | `/health` | 健康檢查 | `http://localhost:8082/health` |

---

##  Client 端 - 插隊 

| Method | Path | 功能 | 範例 |
|--------|------|------|------|
| POST | `/inject` | 插入單筆插隊事件 | `curl -X POST http://localhost:8082/inject -H "Content-Type: application/json" -d '{"env":"A1","text":"Something is wrong","time":"2025-12-31 23:59:59"}'` |
| POST | `/inject-batch` | 插入多筆批次插隊事件 | `curl -X POST http://localhost:8082/inject-batch -H "Content-Type: application/json" -d '{"env":"A1","count":44,"gap":7}'` |

---

## ✅ 測試流程建議

1. 確認服務健康：  
   - `http://localhost:8081/health`  
   - `http://localhost:8082/health`

2. 設定 Webhook：  
   ```bash
   curl "http://localhost:8081/setWebhook?url=http://client/webhook"
