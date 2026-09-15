# แหล่งข้อมูลเกมสำหรับเดโม

ตรวจสอบวันที่ 9 กันยายน 2026 จากหน้าเกมของ Czech Games Edition (CGE)
ภาพกล่องดาวน์โหลดจาก og:image ของแต่ละหน้ามาเก็บใน storage/app/public/boardgames/researched
เครดิตภาพ: Czech Games Edition และเจ้าของงานภาพตามเกมนั้น ๆ ภาพไม่ได้เป็น public domain และการอ้างอิงไม่ได้เท่ากับได้รับใบอนุญาตใช้ภาพทุกวัตถุประสงค์

| เกม / แหล่งข้อมูลและภาพ | ผู้เล่นตามผู้ผลิต | เวลาตามผู้ผลิต | ค่าเวลาในระบบ | ค่าเช่าเดโม (โทเคน/วัน) |
| --- | --- | --- | --- | --- |
| [Codenames (2025)](https://www.czechgames.com/games/codenames) | 4–8+ | 15 นาที | 15 | 30 |
| [Galaxy Trucker (2021)](https://www.czechgames.com/games/galaxy-trucker) | 2–4 | 30 นาที | 30 | 40 |
| [Lost Ruins of Arnak](https://www.czechgames.com/games/lost-ruins-of-arnak) | 1–4 | 30 นาที/ผู้เล่น | 120 (ที่ 4 คน) | 60 |
| [SETI](https://www.czechgames.com/games/seti-search-for-extraterrestrial-intelligence) | 1–4 | 40 นาที/ผู้เล่น | 160 (ที่ 4 คน) | 80 |

ราคาเช่าเป็นค่าที่กำหนดสำหรับเดโม ไม่ใช่ราคาจากผู้ผลิตหรือราคาตลาด หมวดหมู่จับคู่ Party Game = ปาร์ตี้ และ Strategy Game = กลยุทธ์
ระบบเก็บจำนวนผู้เล่นสูงสุดเป็นตัวเลข จึงใช้ 8 สำหรับ Codenames แต่ผู้ผลิตระบุ 8+ ไม่ใช่จำกัด 8 คน
รายการเหล่านี้เป็นข้อมูลเดโม ไม่ใช่การยืนยันว่าร้านมีเกมจริงในสต็อก

## นำเข้าซ้ำ

```bash
docker compose exec -T laravel.test php artisan db:seed --class=ResearchedBoardgamesSeeder
```

ข้ามชื่อที่มีอยู่แล้ว ไม่แก้ราคา รูป หรือสถานะเกมเดิม ไม่เรียก DatabaseSeeder ที่สร้างผู้ใช้ใหม่

## BoardGameGeek

ชื่อเว็บไซต์คือ [BoardGameGeek](https://boardgamegeek.com/) (BGG)
ใช้ค้นหาและเทียบรุ่นของเกมได้ หากจะนำเข้าผ่าน API ให้ศึกษาคู่มือและเงื่อนไข:

- https://boardgamegeek.com/using_the_xml_api
- https://boardgamegeek.com/wiki/page/XML_API_Terms_of_Use

ชุดนี้ใช้หน้าเว็บผู้ผลิตโดยตรง ไม่ได้ใช้ BGG API
