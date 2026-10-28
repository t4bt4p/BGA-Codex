# BoardGameGeek import: 47 games

ชุดข้อมูลนี้นำเข้าจาก board_games.csv ของ TidyTuesday ซึ่งระบุแหล่งข้อมูลจาก BoardGameGeek โดยใช้ชื่อ จำนวนผู้เล่น เวลาเล่น และ URL รูปภาพของเกม

ค่าเช่าเป็นค่าที่กำหนดสำหรับเดโม ไม่ใช่ราคาจาก BGG หรือราคาตลาด

## รูปกล่องจริง (9 กันยายน 2026)

เปลี่ยนภาพ SVG จำลองทั้ง 47 รายการเป็นภาพปก/กล่องสินค้าจริง เก็บไฟล์ใน `storage/app/public/boardgames/real-boxes` และใช้ URL `/storage/boardgames/real-boxes/...` ไม่โหลดภาพจากเว็บภายนอกขณะเปิดหน้าเกม

แหล่งภาพหลัก: [boardgames image collection](https://github.com/jzlung/boardgames/tree/master/gameImages), [Awesome Board Games](https://github.com/edm00se/awesome-board-games), Gamewright, Steve Jackson Games และ Asmodee UK รวมถึงแหล่งเพิ่มเติมที่ระบุรายเกมใน `database/data/real-boardgame-covers.json` พร้อม URL ต้นทาง, SHA-256, ขนาดภาพ และ URL เดิมสำหรับย้อนกลับ ภาพอาจเป็นคนละรุ่นพิมพ์/ภาษากับกล่องที่มีอยู่จริง ควรตรวจรุ่นกับสินค้าจริงก่อนใช้งานร้านค้า

ภาพและเครื่องหมายการค้ายังคงเป็นของเจ้าของสิทธิ์ การเข้าถึงภาพสาธารณะไม่ได้หมายความว่าได้รับสิทธิ์ใช้เชิงพาณิชย์ โปรเจกต์นี้ไม่ได้รับหรือรับรองใบอนุญาตภาพ

หลังนำเข้าเกม ให้ติดตั้งภาพจริงด้วย:

    docker compose exec -T laravel.test php artisan db:seed --class=RealBoardgameCoversSeeder

Seeder ตรวจชนิดไฟล์และ SHA-256 ครบก่อนแก้ฐานข้อมูล อัปเดตเฉพาะภาพจำลอง/ลิงก์ BGG เก่า ไม่เปลี่ยนรูปที่แอดมินอัปโหลดใหม่ ไม่เปลี่ยนชื่อ ราคา หรือสถานะเกม และไม่ลบภาพเดิม สามารถรันซ้ำได้ หากติดตั้งในเครื่องใหม่ต้องดาวน์โหลดได้จากแหล่งเดิม หรือย้ายโฟลเดอร์รูปมาด้วย

คำสั่งนำเข้า:

    docker compose exec -T laravel.test php artisan db:seed --class=BoardGameGeek47Seeder

Seeder จะข้ามชื่อที่มีอยู่แล้ว หากต้องนำเข้าซ้ำจะไม่สร้างแถวซ้ำ
