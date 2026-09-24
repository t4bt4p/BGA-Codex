# BGA-Codex

ระบบ E-Wallet สำหรับเช่าบอร์ดเกม พัฒนาด้วย Laravel, Vue, MySQL และ Laravel Reverb โดยบันทึกธุรกรรมลง Private Blockchain ภายในและยืนยันหลักฐานบน Polygon Amoy

## ความสามารถหลัก

- สมัครสมาชิก จัดการโปรไฟล์ และตรวจสอบยอดโทเคน
- ค้นหาและกรองบอร์ดเกมตามหมวดหมู่ จำนวนผู้เล่น และเวลาเล่น
- เช่า 1–7 วัน และคืนเกมตามกำหนด
- เติมเงินผ่าน PromptPay QR และ Opn webhook
- Admin จัดการเกม ผู้ใช้ การเช่า ธุรกรรม และรายงาน
- อัปเดตสถานะเกมแบบ Real-time ผ่าน Laravel Reverb
- บันทึกธุรกรรมลง Private Blockchain และ Polygon Amoy
- รองรับ Responsive Web และ PWA

## เริ่มต้นระบบ

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
# ตั้ง ADMIN_PASSWORD ใน .env ก่อน seed ครั้งแรก
docker compose up -d
docker compose exec laravel.test php artisan migrate --seed
npm run build
```

เปิดเว็บที่ `http://localhost:8000` หรือตาม `APP_PORT` ใน `.env`

## การเปิดหน้า User บน Desktop และมือถือ

หน้า User รองรับทั้ง Desktop และมือถือ โดย Desktop มีเมนูด้านข้างและรายการเกมหลายคอลัมน์ ส่วนมือถือใช้เมนูด้านล่างตามเดิม

เงื่อนไข `MobileOnly` ยังอยู่ แต่ปิดเป็นค่าเริ่มต้นผ่าน `config/user_access.php` หากต้องการจำกัดเฉพาะมือถืออีกครั้ง ให้ตั้ง `USER_MOBILE_ONLY=true` ใน `.env` แล้วรัน `php artisan config:clear` (หรือคำสั่งเดียวกันผ่าน Docker Compose) ตั้งเป็น `false` เพื่อเปิด Desktop อีกครั้ง หน้า Admin ไม่ถูกจำกัดด้วยเงื่อนไขนี้

## บริการเบื้องหลัง

`compose.yaml` มี MySQL, Laravel, Reverb, Queue worker และ phpMyAdmin การส่งหลักฐานไป Polygon ต้องให้ Queue worker ทำงานตลอดเวลา

รายละเอียดการเชื่อม Polygon อยู่ที่ [docs/POLYGON_AMOY.md](docs/POLYGON_AMOY.md)

ขั้นตอนเปิดรับ PromptPay จริงผ่าน Opn/Omise อยู่ที่ [docs/OPN_LIVE.md](docs/OPN_LIVE.md)

## การทดสอบ

```bash
docker compose exec laravel.test php artisan test
npm run build
```

ห้าม commit `.env`, Private Key, Laravel `APP_KEY` หรือไฟล์กระเป๋าที่เข้ารหัสไว้ใน `storage/app/private/polygon`
