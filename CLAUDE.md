# CLAUDE.md — Instruksi Agent untuk Sistem CKPN (Laravel 13 + Filament 4)

## ⚠️ Aturan Non-Negotiable #1: Konfirmasi Sebelum Operasi Berisiko Kehilangan Data

Sebelum menjalankan **atau menyusun** perubahan apa pun yang berpotensi menghapus/menimpa data — di database, file, maupun repository — kamu **wajib meminta konfirmasi eksplisit dari user dan berhenti sampai disetujui**. Tidak ada pengecualian, meskipun perubahan terlihat kecil.

Contoh operasi yang wajib konfirmasi (daftar lengkap di `AGENTS.md` Bab 13.1):

- DDL destruktif: `DROP TABLE`, `DROP COLUMN`, `TRUNCATE`, perubahan kolom yang berisiko kehilangan nilai.
- DML penghapusan: `DELETE` massal, `Model::truncate()`, hard delete yang menggantikan soft delete.
- Artisan destruktif: `migrate:fresh`, `migrate:refresh`, `db:wipe`, reset seeder yang menimpa data existing.
- Snapshot CKPN: hapus/timpa `pd_netflow_result`, `pd_migration_result`, `lgd_*_result`, `ckpn_*_result`, atau tabel master (PRD Bab 15).
- Queue/Job: `queue:flush`, `queue:clear`, pembatalan batch perhitungan.
- File data: hapus/overwrite file di `storage/app`, `rm -rf` di luar target eksplisit.
- Repository: `git reset --hard`, `git clean -fd`, `git push --force`.

Format konfirmasi sebelum eksekusi (lalu tunggu jawaban user): (1) **Aksi** — perintah persis yang dijalankan, (2) **Dampak** — tabel/file/rentang data terdampak + jumlah baris bila bisa dihitung, (3) **Reversibility** — bisa di-rollback atau permanen, (4) pertanyaan eksplisit "Lanjutkan?".

## Standar Kerja Lengkap

Seluruh standar kerja mengacu pada **`AGENTS.md`** (sumber kebenaran tunggal — jangan duplikasi isinya di sini):

- Prinsip kerja & efisiensi token → `AGENTS.md` Bab 1
- Tech stack & versi → `AGENTS.md` Bab 2
- Struktur direktori domain CKPN → `AGENTS.md` Bab 3
- Standar koding Laravel → `AGENTS.md` Bab 4
- Konvensi Filament 4 → `AGENTS.md` Bab 5
- Referensi domain (detail di `PRD.md`) → `AGENTS.md` Bab 6
- Testing → `AGENTS.md` Bab 7
- Git & commit → `AGENTS.md` Bab 8
- Larangan (Do Not) → `AGENTS.md` Bab 9
- Update `PROGRESS.md` & dokumentasi → `AGENTS.md` Bab 12
- Kebijakan konfirmasi operasi destruktif → `AGENTS.md` Bab 13

---

_Dokumen ini hanya pointer ke `AGENTS.md`. Jika ada konflik, `AGENTS.md` yang berlaku; aturan bisnis/formula mengikuti `PRD.md`._
