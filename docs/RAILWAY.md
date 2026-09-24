# Deploy บน Railway

Repository มี `Dockerfile` สำหรับ build Laravel และ Vue เป็น service เดียว โดยรับ HTTP จากพอร์ตที่ Railway กำหนดและใช้ `/up` เป็น health check

## บริการที่ต้องสร้าง

1. เชื่อม GitHub repository นี้เป็น application service
2. เพิ่ม MySQL service ใน project เดียวกัน
3. สร้าง public domain ให้ application service

## Variables ขั้นต่ำของ application service

```dotenv
APP_NAME=BGA-Codex
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:generated-value
APP_URL=https://your-service.up.railway.app
APP_TIMEZONE=Asia/Bangkok

DB_CONNECTION=mysql
DB_HOST=${{MySQL.MYSQLHOST}}
DB_PORT=${{MySQL.MYSQLPORT}}
DB_DATABASE=${{MySQL.MYSQLDATABASE}}
DB_USERNAME=${{MySQL.MYSQLUSER}}
DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
RUN_MIGRATIONS=true
RUN_SEEDERS=false
```

สร้าง `APP_KEY` ในเครื่องด้วยคำสั่งต่อไปนี้ แล้วคัดลอกเฉพาะผลลัพธ์ไปใส่ Railway Variables:

```bash
php artisan key:generate --show
```

ค่ารูปแบบ `${{MySQL.MYSQLHOST}}` เป็น reference variable ของ Railway ชื่อ service ต้องตรงกับ `MySQL` หากตั้งชื่ออื่นให้เปลี่ยนชื่อใน reference ตามนั้น

เมื่อ deploy และ migration สำเร็จแล้ว เปลี่ยน `RUN_MIGRATIONS=false` เพื่อลดการทำงานซ้ำทุกครั้งที่ restart การ deploy ครั้งที่มี migration ใหม่สามารถเปิดกลับเป็น `true` ชั่วคราวได้

หากต้องการเพิ่มหมวดหมู่ บอร์ดเกมตัวอย่าง และบัญชีผู้ดูแลครั้งแรก ให้ตั้ง `ADMIN_PASSWORD` เป็นรหัสผ่านที่ปลอดภัยและเปลี่ยน `RUN_SEEDERS=true` แล้ว deploy หนึ่งครั้ง Seeder ตรวจข้อมูลเดิมก่อนเพิ่มจึงไม่สร้างรายการซ้ำ หลังสำเร็จให้เปลี่ยน `RUN_SEEDERS=false`

## Opn/Omise

ระหว่างรออนุมัติ Live Mode ให้ใช้ test key และตั้งค่า:

```dotenv
OPN_LIVE_MODE=false
OPN_PUBLIC_KEY=pkey_test_value
OPN_SECRET_KEY=skey_test_value
```

เมื่อ Opn อนุมัติแล้วจึงเปลี่ยนเป็นคีย์จริงและ `OPN_LIVE_MODE=true` ตาม [OPN_LIVE.md](OPN_LIVE.md) ห้ามใส่ Secret Key ใน GitHub หรือ build log
