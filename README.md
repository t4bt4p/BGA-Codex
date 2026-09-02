# BGA-Codex

ระบบ E-Wallet สำหรับเช่าบอร์ดเกม พัฒนาด้วย Laravel, Vue, MySQL และ Laravel Reverb โดยบันทึกธุรกรรมลง Private Blockchain ภายในและยืนยันหลักฐานบน Polygon Amoy

## ความสามารถหลัก

- สมัครสมาชิก จัดการโปรไฟล์ และตรวจสอบยอดโทเคน
- ค้นหาและกรองบอร์ดเกมตามหมวดหมู่ จำนวนผู้เล่น และเวลาเล่น
- เช่า 1–7 วัน คืนเกม และคิดค่าปรับอัตโนมัติ
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

## บริการเบื้องหลัง

`compose.yaml` มี MySQL, Laravel, Reverb, Queue worker และ phpMyAdmin การส่งหลักฐานไป Polygon ต้องให้ Queue worker ทำงานตลอดเวลา

รายละเอียดการเชื่อม Polygon อยู่ที่ [docs/POLYGON_AMOY.md](docs/POLYGON_AMOY.md)

## การทดสอบ

```bash
docker compose exec laravel.test php artisan test
npm run build
```

ห้าม commit `.env`, Private Key, Laravel `APP_KEY` หรือไฟล์กระเป๋าที่เข้ารหัสไว้ใน `storage/app/private/polygon`
