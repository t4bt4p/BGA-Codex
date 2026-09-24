# เปิดใช้ Opn/Omise PromptPay แบบรับเงินจริง

ระบบสร้าง PromptPay QR ที่ฝั่งเซิร์ฟเวอร์ รับ `charge.complete` webhook แล้วเรียก Charge API กลับไปยืนยัน `paid`, จำนวนเงิน, สกุลเงิน, reference และ `livemode` ก่อนเพิ่มโทเค็น การส่ง webhook ซ้ำไม่เพิ่มยอดซ้ำ

## สิ่งที่ต้องมี

- บัญชี Opn Merchant ที่อนุมัติ Live Mode และเปิด PromptPay แล้ว
- บัญชีธนาคารรับเงินที่ผูกกับ Merchant
- URL สาธารณะแบบ HTTPS พร้อมใบรับรองที่ถูกต้อง
- Live Public Key และ Live Secret Key จากหน้า Dashboard ใน Live Mode

## ค่าบนเซิร์ฟเวอร์

ตั้งค่าใน `.env` ของเซิร์ฟเวอร์เท่านั้น ห้าม commit คีย์:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example

OPN_LIVE_MODE=true
OPN_PUBLIC_KEY=pkey_live_value_from_dashboard
OPN_SECRET_KEY=skey_live_value_from_dashboard
```

ชื่อคีย์จริงไม่มีคำว่า `live`; ตัวอย่างด้านบนเป็นเพียง placeholder ให้คัดลอกค่าจาก Dashboard โดยตรง หลังแก้ `.env` ให้รัน:

```bash
php artisan optimize:clear
php artisan config:cache
```

ตรวจคีย์และสิทธิ์ PromptPay โดยไม่สร้าง Charge:

```bash
php artisan opn:check --remote
```

คำสั่งต้องรายงาน `Opn mode: LIVE` และ `PromptPay/THB is available` จึงค่อยทดสอบเงินจริง

## Webhook

เพิ่ม endpoint ใน Opn Dashboard ฝั่ง Live Mode:

```text
https://your-domain.example/api/webhooks/opn
```

Opn กำหนดให้ webhook เป็น HTTPS และใบรับรองต้องไม่ใช่ self-signed ระบบไม่เชื่อข้อมูลใน webhook โดยตรง แต่ใช้ Charge ID เรียก Opn API กลับไปตรวจซ้ำก่อนเพิ่มยอด

## ทดสอบเงินจริง

1. ใช้บัญชีสมาชิกสร้าง QR จำนวน 20 บาท
2. ตรวจใน Opn Dashboard ว่า Charge เป็น Live Mode และสถานะเริ่มต้นเป็น pending
3. สแกน QR ด้วยแอปธนาคารและตรวจชื่อผู้รับกับยอดก่อนยืนยัน
4. หลังจ่าย ตรวจว่า Charge เป็น successful และหน้าเว็บเพิ่ม 20 โทเค็นเพียงครั้งเดียว
5. ส่ง webhook ซ้ำจาก Dashboard แล้วตรวจว่ายอดไม่เพิ่มครั้งที่สอง
6. ตรวจ `Topup_request_tb`, `Transaction_tb` และยอดกระเป๋าว่าอ้างอิงรายการเดียวกัน

เริ่มด้วยยอดขั้นต่ำ 20 บาทตามตัวเลือกที่ระบบรองรับ ห้ามใช้ Live Secret Key ในเครื่องที่แชร์หรือแสดงคีย์บนหน้าจอระหว่างนำเสนอ
