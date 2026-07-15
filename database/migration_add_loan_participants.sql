-- Migration: tabel loan_participants — personel (IT Staff) yang dilibatkan
-- dalam sebuah acara peminjaman. Aman diulang (IF NOT EXISTS).

USE bmn_streaming;

CREATE TABLE IF NOT EXISTS loan_participants (
    loan_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (loan_id, user_id),
    CONSTRAINT fk_lp_loan FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE,
    CONSTRAINT fk_lp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
