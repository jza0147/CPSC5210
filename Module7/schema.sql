-- CPSC 5210 Final Project - Auction Database Schema
-- Run this once to create the database and all tables.

CREATE DATABASE IF NOT EXISTS auction;
USE auction;

-- ---------------------------------------------------------
-- Donors
-- ---------------------------------------------------------
CREATE TABLE donors (
  donor_id    INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100) NOT NULL,
  address     VARCHAR(200),
  phone       VARCHAR(20)
) ENGINE=InnoDB AUTO_INCREMENT = 5000;

-- ---------------------------------------------------------
-- Bidders
-- paid is just a quick "settled up?" flag. The actual payment
-- details (method, amount, check number) live in the payments
-- table below, one row per checkout.
-- ---------------------------------------------------------
CREATE TABLE bidders (
  bidder_id       INT AUTO_INCREMENT PRIMARY KEY,
  paddle_number   SMALLINT UNSIGNED NULL UNIQUE
                  CHECK (paddle_number BETWEEN 1 AND 999),  -- typed in at registration, not sequential
  name            VARCHAR(100) NOT NULL,
  address         VARCHAR(200),
  phone           VARCHAR(20),
  paid            TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB AUTO_INCREMENT = 9000;

-- ---------------------------------------------------------
-- Items
-- status flips to 'closed' when a winner is entered.
-- paid flips to 1 when that item's bidder checks out.
-- ---------------------------------------------------------
CREATE TABLE items (
  item_id                INT AUTO_INCREMENT PRIMARY KEY,
  name                   VARCHAR(150) NOT NULL,
  description            TEXT,
  section                VARCHAR(50) NULL UNIQUE,   -- how the item is labeled, sorted and found; one item per section
  price                  DECIMAL(10,2),
  suggested_start_price  DECIMAL(10,2),
  donor_id               INT,
  status                 ENUM('open','closed') NOT NULL DEFAULT 'open',
  winner_id              INT NULL,
  winning_bid            DECIMAL(10,2) NULL,
  won_at                 DATETIME(3) NULL,   -- when the winner was entered; drives the projector feed
  paid                   TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (donor_id)  REFERENCES donors(donor_id),
  FOREIGN KEY (winner_id) REFERENCES bidders(bidder_id)
) ENGINE=InnoDB AUTO_INCREMENT = 1000;

-- ---------------------------------------------------------
-- Payments
-- One row per checkout transaction.
--   amount   = the TOTAL the bidder paid (items + card_fee)
--   card_fee = the credit card fee portion; 0 unless method = 'card'
--   paid_at  = when it happened
-- ---------------------------------------------------------
CREATE TABLE payments (
  payment_id    INT AUTO_INCREMENT PRIMARY KEY,
  bidder_id     INT NOT NULL,
  method        ENUM('cash','check','card') NOT NULL,
  check_number  VARCHAR(30) NULL,
  amount        DECIMAL(10,2) NOT NULL,
  card_fee      DECIMAL(10,2) NOT NULL DEFAULT 0,
  paid_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (bidder_id) REFERENCES bidders(bidder_id)
) ENGINE=InnoDB;
