# การแทนที่ QR ใน Opn Test Mode

เปิดด้วย `OPN_REPLACE_PENDING_TEST_CHARGES=true` พร้อม `OPN_LIVE_MODE=false` และ Test Secret Key

เมื่อสร้าง QR ใหม่ ระบบล็อกการสร้างรายการของผู้ใช้คนเดียวกัน ตรวจสอบรายการ pending เดิมกับ Opn แล้วเรียก `POST /charges/{id}/mark_as_failed` จากนั้นอ่านสถานะกลับมายืนยัน ก่อนบันทึก `cancelled` และสร้าง Charge ใหม่ หากยกเลิกไม่ได้ จะไม่สร้าง Charge ใหม่ หากรายการเดิมจ่ายสำเร็จระหว่างนั้น จะเติมตามยอดเดิมหนึ่งครั้งและหยุดการสร้างรายการใหม่

นี่คือการจำลองการยกเลิกสำหรับ sandbox เท่านั้น ไม่ใช่ API ยกเลิก PromptPay จริง เมื่อเปิด Live Mode จะไม่เรียก mark_as_failed และยังใช้พฤติกรรมเดิมที่แต่ละ Charge แยกจากกัน

## ทดสอบยอมรับ

1. สร้าง QR 100 บาท เก็บ Charge ID ไว้โดยยังไม่กด Paid
2. เลือก 500 บาทแล้วสร้าง QR ใหม่
3. Charge 100 บาทต้องเป็น failed ที่ Opn และ cancelled ในระบบ
4. ลอง mark_as_paid ของ Charge 100: Opn ต้องปฏิเสธ ยอดโทเค่นไม่เพิ่ม
5. mark_as_paid ของ Charge 500: ระบบเติม 500 โทเค่นเพียงครั้งเดียว
6. ตรวจซ้ำหรือส่ง webhook ซ้ำ: ยอดต้องไม่เพิ่มซ้ำ

อ้างอิง: https://docs.omise.co/charges-api (Mark a charge as failed / Mark a charge as paid)
