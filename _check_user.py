import sqlite3
con = sqlite3.connect(r"storage/payroll.sqlite")
cur = con.cursor()
rows = cur.execute("SELECT id,username,role,is_active FROM users WHERE username='ceo01'").fetchall()
print("\n".join("|".join(map(str, r)) for r in rows) or "NO_ROWS")
