# Backend — หน้าแอดมิน, API และ DB

- `admin/`: Vue สำหรับผู้ดูแลระบบเท่านั้น
- `app/`, `routes/`: Laravel API และเส้นทางแอดมิน
- `database/`: migrations และ seeders สำหรับสร้างฐานข้อมูล
- `compose.yaml`: MySQL 8.4 พร้อม volume, API, admin, frontend, queue, scheduler และ reverb

ส่งโฟลเดอร์นี้พร้อม `frontend/` วางข้างกัน ไม่ต้องส่งไฟล์ที่ root ของ repo

## Docker Compose

ต้องมี Docker พร้อม Compose; บน WSL ให้เปิด Docker Desktop WSL integration

จากโฟลเดอร์ backend สำหรับ checkout ใหม่:

```bash
cp .env.example .env
cp .env.compose.example .env.compose
```

หากมี `.env` อยู่แล้วให้เก็บค่าเดิม และตรวจ APP_KEY, payment, Polygon และ Reverb ให้เหมาะกับ environment

```bash
docker compose --env-file .env.compose build
docker compose --env-file .env.compose run --rm backend php artisan key:generate --show
```

ถ้า APP_KEY ยังว่าง ให้นำค่าที่ได้ใส่ใน `.env` จากนั้น:

```bash
docker compose --env-file .env.compose run --rm backend php artisan migrate --force
docker compose --env-file .env.compose up -d
```

- ลูกค้า: http://localhost:5173
- แอดมิน: http://localhost:5173/admin (หรือ http://localhost:8000/admin)
- API health: http://localhost:8000/up
- MySQL จากเครื่อง: 127.0.0.1:3307; ภายใน containers: db:3306

DB credentials ใน `.env.compose` ใช้กับ MySQL และ override ค่า DB ของ API ส่วนข้อมูลระบบภายนอกใช้ `.env` ตั้ง REVERB_APP_KEY ใน `.env.compose` ให้ตรง `.env` เพื่อเปิด realtime แล้ว build ใหม่

MySQL เก็บข้อมูลใน `mysql-data`; ไฟล์อัปโหลดและ ledger เก็บใน `backend-storage` การ `down` เก็บ volumes ไว้ แต่ `down -v` ลบข้อมูล ฐานข้อมูล container เป็นฐานข้อมูลใหม่ ต้อง import เองหากต้องใช้ข้อมูลเดิม การแก้ password ใน env ไม่เปลี่ยนบัญชีใน MySQL volume ที่มีอยู่แล้ว

## รันพัฒนาโดยไม่ใช้ Docker

```bash
composer install
npm ci
cd admin
npm ci
npm run build
cd ..
php artisan serve --host=127.0.0.1 --port=8000
```

เปิด /admin บน port 8000 เพื่อใช้งานแอดมินที่ build แล้ว หรือ `cd admin && npm run dev` แล้วเปิด http://localhost:5174/admin เพื่อพัฒนาพร้อม hot reload (API ยังต้องรัน port 8000)

ถ้า PHP ในเครื่องเชื่อม MySQL container ให้ตั้ง `.env` เป็น DB_HOST=127.0.0.1, DB_PORT=3307 และ credentials ให้ตรง `.env.compose`

## ตรวจสอบ

```bash
cd admin
npm run build
npm test
cd ..
php artisan test
```

ก่อนส่งงานไม่ต้องรวม vendor, node_modules, .env, .env.compose, database/*.sqlite หรือ storage ที่มีข้อมูลจริง ผู้รับสร้าง DB จาก migrations ได้ เอกสารก่อนแยกอยู่ใน docs/ และอาจอ้าง path เดิม

Railway: ใช้ Root Directory `backend` กับ Dockerfile ในโฟลเดอร์นี้ สำหรับ frontend ที่ deploy แยกต้องปรับ Nginx upstream ให้ชี้ API ที่ใช้งานจริง
