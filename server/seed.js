import fs from 'node:fs/promises'
import mysql from 'mysql2/promise'

if (!process.env.DATABASE_URL) {
  console.log('DATABASE_URL is not set; the app will use its demo data fallback.')
  process.exit(0)
}

const connection = await mysql.createConnection({ uri: process.env.DATABASE_URL, ssl: { rejectUnauthorized: false } })
try {
  const schema = await fs.readFile(new URL('./schema.sql', import.meta.url), 'utf8')
  for (const statement of schema.split(';').map((part) => part.trim()).filter(Boolean)) await connection.query(statement)
  console.log('SmartGym schema is ready. Add organization-specific seed data through the API or SQL client.')
} finally {
  await connection.end()
}
