-- MySQL Workbench Forward Engineering

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- -----------------------------------------------------
-- Schema mydb
-- -----------------------------------------------------
-- -----------------------------------------------------
-- Schema bowlingcenter
-- -----------------------------------------------------

-- -----------------------------------------------------
-- Schema bowlingcenter
-- -----------------------------------------------------
CREATE SCHEMA IF NOT EXISTS `bowlingcenter` DEFAULT CHARACTER SET utf8mb4 ;
USE `bowlingcenter` ;

-- -----------------------------------------------------
-- Table `bowlingcenter`.`users`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bowlingcenter`.`users` (
  `id` INT(10) UNSIGNED NOT NULL,
  `first_name` VARCHAR(150) NOT NULL,
  `last_name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `password_hash` VARCHAR(45) NOT NULL,
  `phone_number` INT NOT NULL,
  `role` VARCHAR(150) NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`))
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4;


-- -----------------------------------------------------
-- Table `bowlingcenter`.`directie`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bowlingcenter`.`directie` (
  `id` INT(10) UNSIGNED NOT NULL,
  `first_name` VARCHAR(150) NOT NULL,
  `last_name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `password_hash` VARCHAR(45) NOT NULL,
  `phone_number` INT NOT NULL,
  `role` VARCHAR(150) NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`))
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4;


-- -----------------------------------------------------
-- Table `bowlingcenter`.`employee`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bowlingcenter`.`employee` (
  `id` INT(10) UNSIGNED NOT NULL,
  `first_name` VARCHAR(150) NOT NULL,
  `last_name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `password_hash` VARCHAR(45) NOT NULL,
  `phone_number` INT NOT NULL,
  `role` VARCHAR(150) NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`))
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4;


-- -----------------------------------------------------
-- Table `bowlingcenter`.`reservations`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bowlingcenter`.`reservations` (
  `id` INT NOT NULL,
  `date` DATE NULL,
  `start_time` TIME NULL,
  `end_time` TIME NULL,
  `number_of_adults` INT NULL,
  `number_of_kids` INT NULL,
  `notes` VARCHAR(150) NULL,
  `status` TINYINT NULL,
  `created_by_user_id` VARCHAR(45) NULL,
  `last_modified_by_user_id` VARCHAR(45) NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  `deleted_at` DATETIME NULL,
  `employee_id` INT(10) UNSIGNED NOT NULL,
  `users_id` INT(10) UNSIGNED NOT NULL,
  `directie_id` INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`id`, `employee_id`, `users_id`, `directie_id`),
  INDEX `fk_reservations_employee1_idx` (`employee_id` ASC) VISIBLE,
  INDEX `fk_reservations_users1_idx` (`users_id` ASC) VISIBLE,
  INDEX `fk_reservations_directie1_idx` (`directie_id` ASC) VISIBLE,
  CONSTRAINT `fk_reservations_employee1`
    FOREIGN KEY (`employee_id`)
    REFERENCES `bowlingcenter`.`employee` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_reservations_users1`
    FOREIGN KEY (`users_id`)
    REFERENCES `bowlingcenter`.`users` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_reservations_directie1`
    FOREIGN KEY (`directie_id`)
    REFERENCES `bowlingcenter`.`directie` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `bowlingcenter`.`lane`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bowlingcenter`.`lane` (
  `id` INT UNSIGNED NOT NULL,
  `lane_number` VARCHAR(45) NULL,
  `status` VARCHAR(45) NULL,
  `kinder` VARCHAR(45) NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `bowlingcenter`.`reervation_lane`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bowlingcenter`.`reervation_lane` (
  `id` INT UNSIGNED NOT NULL,
  `reservation_id` INT UNSIGNED NOT NULL,
  `lane_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NULL,
  `reservations_employee_id` INT(10) UNSIGNED NOT NULL,
  `reservations_user_id` INT(10) UNSIGNED NOT NULL,
  `reservations_derectie_id` INT(10) UNSIGNED NOT NULL,
  `lane_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`, `reservation_id`, `lane_id`, `reservations_employee_id`, `reservations_user_id`, `reservations_derectie_id`, `lane_id`),
  INDEX `fk_reervation_lane_reservations1_idx` (`reservations_employee_id` ASC, `reservations_user_id` ASC, `reservations_derectie_id` ASC) VISIBLE,
  INDEX `fk_reervation_lane_lane1_idx` (`lane_id` ASC) VISIBLE,
  CONSTRAINT `fk_reervation_lane_reservations1`
    FOREIGN KEY (`reservations_employee_id` , `reservations_user_id` , `reservations_derectie_id`)
    REFERENCES `bowlingcenter`.`reservations` (`employee_id` , `users_id` , `directie_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_reervation_lane_lane1`
    FOREIGN KEY (`lane_id`)
    REFERENCES `bowlingcenter`.`lane` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `bowlingcenter`.`extras`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bowlingcenter`.`extras` (
  `id` INT UNSIGNED NOT NULL,
  `reservation_id` INT UNSIGNED NOT NULL,
  `snackpakket_basis` TINYINT NULL,
  `snackpakket_luxe` TINYINT NULL,
  `vrijgezellenfeest` TINYINT NULL,
  `kinderpartij` TINYINT NULL,
  `reservations_id` INT NOT NULL,
  `reservations_user_id` INT NOT NULL,
  `reservations_employee_id` INT(10) UNSIGNED NOT NULL,
  `reservations_users_id` INT(10) UNSIGNED NOT NULL,
  `reservations_directie_id` INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`id`, `reservation_id`, `reservations_id`, `reservations_user_id`, `reservations_employee_id`, `reservations_users_id`, `reservations_directie_id`),
  INDEX `fk_extras_reservations1_idx` (`reservations_id` ASC, `reservations_user_id` ASC, `reservations_employee_id` ASC, `reservations_users_id` ASC, `reservations_directie_id` ASC) VISIBLE,
  CONSTRAINT `fk_extras_reservations1`
    FOREIGN KEY (`reservations_id` , `reservations_employee_id` , `reservations_users_id` , `reservations_directie_id`)
    REFERENCES `bowlingcenter`.`reservations` (`id` , `employee_id` , `users_id` , `directie_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `bowlingcenter`.`game`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bowlingcenter`.`game` (
  `id` INT UNSIGNED NOT NULL,
  `reservations_id` INT NOT NULL,
  `reservations_user_id` INT NOT NULL,
  PRIMARY KEY (`id`, `reservations_id`, `reservations_user_id`),
  INDEX `fk_game_reservations1_idx` (`reservations_id` ASC, `reservations_user_id` ASC) VISIBLE,
  CONSTRAINT `fk_game_reservations1`
    FOREIGN KEY (`reservations_id`)
    REFERENCES `bowlingcenter`.`reservations` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `bowlingcenter`.`player`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bowlingcenter`.`player` (
  `id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(45) NULL,
  PRIMARY KEY (`id`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `bowlingcenter`.`game_has_players`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bowlingcenter`.`game_has_players` (
  `player_id` INT UNSIGNED NOT NULL,
  `game_id` INT UNSIGNED NOT NULL,
  `game_reservation_id` INT UNSIGNED NOT NULL,
  `game_reservations_reservation_id` INT NOT NULL,
  `game_reservations_user_id` INT NOT NULL,
  PRIMARY KEY (`player_id`, `game_id`, `game_reservation_id`, `game_reservations_reservation_id`, `game_reservations_user_id`),
  INDEX `fk_game_has_players_player1_idx` (`player_id` ASC) VISIBLE,
  INDEX `fk_game_has_players_game1_idx` (`game_id` ASC, `game_reservation_id` ASC, `game_reservations_reservation_id` ASC, `game_reservations_user_id` ASC) VISIBLE,
  CONSTRAINT `fk_game_has_players_player1`
    FOREIGN KEY (`player_id`)
    REFERENCES `bowlingcenter`.`player` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_game_has_players_game1`
    FOREIGN KEY (`game_id` , `game_reservations_reservation_id` , `game_reservations_user_id`)
    REFERENCES `bowlingcenter`.`game` (`id` , `reservations_id` , `reservations_user_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
