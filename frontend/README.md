# Frontend — หน้าลูกค้า

มีเฉพาะ Vue หน้าลูกค้า; หน้าแอดมิน, API และฐานข้อมูลอยู่ใน `../backend` ส่งสองโฟลเดอร์ `frontend` กับ `backend` วางข้างกัน โดยไม่ต้องใช้ไฟล์ที่ root ของ repo

## รันพัฒนา

```bash
npm ci
npm run dev
```

เปิด http://localhost:5173 โดย backend ต้องเปิดที่ http://127.0.0.1:8000 หากใช้ที่อยู่อื่นให้รัน `API_PROXY_TARGET=http://host:port npm run dev`

`/api`, `/storage` และ `/admin` ส่งต่อไป backend; หน้าลูกค้าไม่มี source ของหน้าแอดมิน

## Docker

ใช้ Compose ใน `../backend` ตาม backend/README.md

## ตรวจสอบ

```bash
npm run build
npm test
```

ไฟล์ `.env.example` มีเฉพาะค่าฝั่ง browser ห้ามใส่ secrets ลง VITE_* ก่อนส่งงานไม่ต้องรวม node_modules, dist หรือ .env
