BEGIN TRANSACTION;
CREATE TABLE IF NOT EXISTS "employees" (
	"id"	INTEGER,
	"emp_code"	TEXT NOT NULL UNIQUE,
	"name"	TEXT NOT NULL,
	"department"	TEXT NOT NULL,
	"email"	TEXT NOT NULL,
	"line_user_id"	TEXT DEFAULT ,
	"is_manager"	INTEGER NOT NULL DEFAULT 0,
	"is_active"	INTEGER NOT NULL DEFAULT 1,
	"created_at"	TEXT NOT NULL,
	"address"	TEXT NOT NULL DEFAULT ,
	"bank_account"	TEXT NOT NULL DEFAULT ,
	"position"	TEXT NOT NULL DEFAULT Staff,
	"national_id"	TEXT NOT NULL DEFAULT ,
	"start_date"	TEXT NOT NULL DEFAULT ,
	"starting_base_salary"	REAL NOT NULL DEFAULT 0,
	"initial_base_salary"	REAL NOT NULL DEFAULT 0,
	"bank_name"	TEXT NOT NULL DEFAULT ,
	"end_date"	TEXT DEFAULT NULL,
	"resignation_reason"	TEXT DEFAULT NULL,
	"sick_leave_quota"	INTEGER NOT NULL DEFAULT 30,
	"annual_leave_quota"	INTEGER NOT NULL DEFAULT 6,
	PRIMARY KEY("id" AUTOINCREMENT)
);
CREATE TABLE IF NOT EXISTS "payroll_runs" (
	"id"	INTEGER,
	"employee_id"	INTEGER NOT NULL,
	"month"	INTEGER NOT NULL,
	"year"	INTEGER NOT NULL,
	"base_salary"	REAL NOT NULL,
	"overtime"	REAL NOT NULL DEFAULT 0,
	"bonus"	REAL NOT NULL DEFAULT 0,
	"deductions"	REAL NOT NULL DEFAULT 0,
	"net_salary"	REAL NOT NULL,
	"status"	TEXT NOT NULL DEFAULT draft CHECK("status" IN ("draft", "paid")),
	"paid_at"	TEXT,
	"paid_by"	INTEGER,
	"slip_sent_at"	TEXT,
	"slip_sent_by"	INTEGER,
	"slip_channel"	TEXT,
	"notes"	TEXT DEFAULT ,
	"created_by"	INTEGER NOT NULL,
	"created_at"	TEXT NOT NULL,
	"updated_at"	TEXT NOT NULL,
	"other_deductions"	REAL NOT NULL DEFAULT 0,
	"social_security"	REAL NOT NULL DEFAULT 0,
	"withholding_tax"	REAL NOT NULL DEFAULT 0,
	"social_security_deduction"	REAL NOT NULL DEFAULT 0,
	"late_deduction"	REAL NOT NULL DEFAULT 0,
	"absence_deduction"	REAL NOT NULL DEFAULT 0,
	"welfare_loan_deduction"	REAL NOT NULL DEFAULT 0,
	"input_base_salary"	REAL NOT NULL DEFAULT 0,
	"prorated_base_salary"	REAL NOT NULL DEFAULT 0,
	"progressive_rate"	REAL NOT NULL DEFAULT 0,
	"progressive_amount"	REAL NOT NULL DEFAULT 0,
	"severance_pay"	REAL NOT NULL DEFAULT 0,
	"leave_encashment"	REAL NOT NULL DEFAULT 0,
	UNIQUE("employee_id","month","year"),
	PRIMARY KEY("id" AUTOINCREMENT),
	FOREIGN KEY("created_by") REFERENCES "users_old"("id"),
	FOREIGN KEY("employee_id") REFERENCES "employees"("id"),
	FOREIGN KEY("paid_by") REFERENCES "users_old"("id"),
	FOREIGN KEY("slip_sent_by") REFERENCES "users_old"("id")
);
CREATE TABLE IF NOT EXISTS "users" (
	"id"	INTEGER,
	"username"	TEXT NOT NULL UNIQUE,
	"password_hash"	TEXT NOT NULL,
	"role"	TEXT NOT NULL CHECK("role" IN ("admin_it", "hr", "accounting", "ceo")),
	"full_name"	TEXT NOT NULL,
	"is_active"	INTEGER NOT NULL DEFAULT 1,
	"password_changed_at"	TEXT DEFAULT NULL,
	"created_at"	TEXT NOT NULL,
	PRIMARY KEY("id" AUTOINCREMENT)
);
INSERT INTO "employees" VALUES (1,'EMP001','สมชัย มีดี','Production','samitanunkongrod@gmail.com','',0,1,'2026-04-18 11:14:35','คลงหก','46701','Staff','1680403063397','2026-04-18',0.0,16000.0,'SCB',NULL,NULL,30,6);
INSERT INTO "employees" VALUES (2,'EMP002','สุดา คงรอด','Sales','suda@example.com','',0,1,'2026-04-18 11:14:35','คลองหก','67063','Staff','1670603104714','2026-04-18',0.0,16000.0,'GSB',NULL,NULL,30,6);
INSERT INTO "employees" VALUES (3,'MGR001','Manager A.','Management','manager.a@example.com','',1,1,'2026-04-18 11:14:35','','','Manager','','2026-04-18',0.0,0.0,'',NULL,NULL,30,6);
INSERT INTO "employees" VALUES (4,'EMP003','ศิริชัย เฉลิมพันธ์','IT','samitanunkongrod@gmail.com','tonkawbz',0,1,'2026-04-18 15:15:05','คลองหนึ่ง','67030','Staff','1670101301817','2026-04-18',0.0,16000.0,'KTB',NULL,NULL,30,6);
INSERT INTO "employees" VALUES (5,'EMP004','ธนภูมิ แก้วยา','Sales','tkkongrod@gmail.com','',0,1,'2026-04-19 09:33:01','คลองหนึ่ง','67031','Staff','1670101301833','2026-04-19',0.0,16000.0,'KTB',NULL,NULL,30,6);
INSERT INTO "employees" VALUES (6,'EMP005','ซาร่า โกบอล','Production','tonkhawsami@gmail.com','',0,1,'2026-04-20 03:45:43','คลองสาม','67000','Staff','1670401304814','2023-11-07',0.0,16000.0,'BBL',NULL,NULL,30,6);
INSERT INTO "employees" VALUES (7,'EMP006','แสนดี มั่งมี','Production','tonton8748@gmail.com','',0,1,'2026-04-21 03:48:36','คลงหก','67040','Staff','1670304604917','2025-03-20',0.0,16000.0,'GSB',NULL,NULL,30,6);
INSERT INTO "employees" VALUES (8,'EMP007','ธีรดา สีฤทธิ์','Programmer','samikhkg48@gmail.com','',0,1,'2026-04-22 08:54:27','คลองสาม','67011','Staff','1167104160377','2025-07-13',0.0,17000.0,'GSB',NULL,NULL,30,6);
INSERT INTO "employees" VALUES (9,'EMP008','สมิตานัน คงรอด','Programm','samitanunkongrod@gmail.com','',0,1,'2026-04-23 08:26:21','คลองหก','67017','Staff','1670201106501','2025-04-20',0.0,17000.0,'KTB',NULL,NULL,30,6);
INSERT INTO "employees" VALUES (10,'EMP009','กรภัทร สุขพลาย','Marketing','samitanunkongrod@gmail.com','',0,1,'2026-04-24 06:09:08','คลองหก','67088','Staff','1670209874461','2026-04-24',0.0,16500.0,'GSB',NULL,NULL,30,6);
INSERT INTO "payroll_runs" VALUES (1,4,4,2026,16000.0,1.0,1000.0,800.0,16201.0,'paid','2026-04-18 15:17:23',3,'2026-05-12 17:53:18',3,'email','',2,'2026-04-18 15:15:37','2026-05-12 17:53:18',0.0,0.0,0.0,800.0,0.0,0.0,0.0,16000.0,16000.0,0.0,0.0,0.0,0.0);
INSERT INTO "payroll_runs" VALUES (2,1,4,2026,16000.0,300.0,0.0,800.0,15500.0,'paid','2026-04-19 15:03:41',3,'2026-04-24 09:56:10',3,'email','',2,'2026-04-19 14:31:17','2026-04-24 09:56:10',0.0,0.0,0.0,800.0,0.0,0.0,0.0,16000.0,16000.0,0.0,0.0,0.0,0.0);
INSERT INTO "payroll_runs" VALUES (3,5,4,2026,16000.0,100.0,0.0,800.0,15300.0,'paid','2026-04-19 15:01:10',3,'2026-04-24 09:56:15',3,'email','',2,'2026-04-19 14:33:25','2026-04-24 09:56:15',0.0,0.0,0.0,800.0,0.0,0.0,0.0,16000.0,16000.0,0.0,0.0,0.0,0.0);
INSERT INTO "payroll_runs" VALUES (4,4,5,2026,17000.0,300.0,0.0,850.0,16450.0,'paid','2026-04-19 15:48:45',3,'2026-05-12 14:48:15',3,'email','',2,'2026-04-19 15:29:22','2026-05-12 14:48:15',0.0,0.0,0.0,850.0,0.0,0.0,0.0,17000.0,17000.0,0.0,0.0,0.0,0.0);
INSERT INTO "payroll_runs" VALUES (5,6,4,2026,19250.0,300.0,0.0,875.0,18675.0,'paid','2026-04-20 09:20:49',3,'2026-04-24 09:56:25',3,'email','',2,'2026-04-20 08:47:31','2026-04-24 09:56:25',0.0,0.0,0.0,875.0,0.0,0.0,0.0,0.0,0.0,0.0,0.0,0.0,0.0);
INSERT INTO "payroll_runs" VALUES (6,7,4,2026,16800.0,300.0,0.0,840.0,16260.0,'paid','2026-04-21 08:53:53',3,'2026-04-24 09:56:31',3,'email','',2,'2026-04-21 08:53:13','2026-04-24 09:56:31',0.0,0.0,0.0,840.0,0.0,0.0,0.0,0.0,0.0,0.0,0.0,0.0,0.0);
INSERT INTO "payroll_runs" VALUES (7,8,4,2026,17000.0,100.0,300.0,850.0,16550.0,'paid','2026-04-23 08:37:43',3,'2026-04-24 09:56:38',3,'email','',2,'2026-04-22 13:55:23','2026-04-24 09:56:38',0.0,0.0,0.0,850.0,0.0,0.0,0.0,0.0,0.0,0.0,0.0,0.0,0.0);
INSERT INTO "payroll_runs" VALUES (8,9,4,2026,17850.0,300.0,0.0,875.0,17275.0,'paid','2026-04-23 15:02:00',3,'2026-05-12 14:50:17',3,'email','',2,'2026-04-23 13:26:52','2026-05-12 14:50:17',0.0,0.0,0.0,875.0,0.0,0.0,0.0,0.0,0.0,0.0,0.0,0.0,0.0);
INSERT INTO "payroll_runs" VALUES (9,2,4,2026,6933.33,300.0,0.0,346.67,6886.66,'paid','2026-04-23 16:11:40',3,'2026-04-24 09:56:48',3,'email','',2,'2026-04-23 16:10:48','2026-04-24 09:56:48',0.0,0.0,0.0,346.67,0.0,0.0,0.0,0.0,0.0,0.0,0.0,0.0,0.0);
INSERT INTO "payroll_runs" VALUES (10,10,4,2026,3850.0,0.0,300.0,192.5,3957.5,'paid','2026-04-24 11:23:39',3,'2026-04-24 11:24:02',3,'email','',2,'2026-04-24 11:09:29','2026-04-24 11:24:02',0.0,0.0,0.0,192.5,0.0,0.0,0.0,0.0,0.0,0.0,0.0,0.0,0.0);
INSERT INTO "users" VALUES (1,'itadmin','$2y$10$R7RPNSdm82NygqTgowW9MuEKZ0DjC96gaap0e9bbMG3DppsVjMYca','admin_it','IT Administrator',1,'2026-04-18 11:14:35','2026-04-18 11:14:35');
INSERT INTO "users" VALUES (2,'hr01','$2y$10$oXwfmcOHiWeMGFtVNNTBb.QBwiUDx.jIgWPLgzBObGHVwE9PnK/di','hr','HR Officer',1,'2026-04-21 05:07:24','2026-04-18 11:14:35');
INSERT INTO "users" VALUES (3,'acc01','$2y$10$QEer8QwvMGQyiyO.elqDFOar31q.26kr1.b5WsJs.PuVsDspovkEu','accounting','Accounting Officer',1,'2026-04-18 11:14:35','2026-04-18 11:14:35');
INSERT INTO "users" VALUES (4,'ceo01','$2y$10$sbODGWg.oB28MqGybNu7XOqPGo/ngelHWJ6DaCt7ZNB6dtfiBSIom','ceo','Chief Executive Officer',1,'2026-04-23 04:14:47','2026-04-23 04:14:47');
COMMIT;
