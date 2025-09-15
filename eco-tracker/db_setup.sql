SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `eco_tracker_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `eco_tracker_db`;

CREATE TABLE `habits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) NOT NULL,
  `icon` varchar(10) NOT NULL,
  `points` int(11) NOT NULL DEFAULT 10,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `habits` (`id`, `name`, `description`, `icon`, `points`) VALUES
(1, 'Use Reusable Bag', 'Avoid plastic bags when shopping.', '🛍️', 10),
(2, 'Shorter Showers', 'Cut your shower time by 2 minutes.', '🚿', 15),
(3, 'Meatless Monday', 'Skip meat for one day a week.', '🥗', 20),
(4, 'Unplug Electronics', 'Unplug chargers and devices when not in use.', '🔌', 5),
(5, 'Use Public Transport', 'Take the bus or train instead of driving.', '🚌', 25),
(6, 'Recycle Properly', 'Sort your waste and recycle materials correctly.', '♻️', 10);

CREATE TABLE `user_habits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `habit_id` int(11) NOT NULL,
  `date_completed` date NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_habit_date` (`user_id`,`habit_id`,`date_completed`),
  KEY `habit_id` (`habit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `awards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) NOT NULL,
  `icon` varchar(10) NOT NULL,
  `type` enum('total','streak') NOT NULL,
  `value` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `awards` (`id`, `name`, `description`, `icon`, `type`, `value`) VALUES
(1, 'Eco Starter', 'Track your very first habit!', '🌱', 'total', 1),
(2, 'Green Thumb', 'Track 10 total habits.', '🌳', 'total', 10),
(3, 'Committed', 'Maintain a 3-day tracking streak.', '🥉', 'streak', 3),
(4, 'Eco Warrior', 'Maintain a 7-day tracking streak.', '🥈', 'streak', 7),
(5, 'Planet Hero', 'Track 50 total habits.', '🥇', 'total', 50);

CREATE TABLE `user_awards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `award_id` int(11) NOT NULL,
  `date_earned` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_award` (`user_id`,`award_id`),
  KEY `award_id` (`award_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `user_habits`
  ADD CONSTRAINT `user_habits_ibfk_1` FOREIGN KEY (`habit_id`) REFERENCES `habits` (`id`) ON DELETE CASCADE;

ALTER TABLE `user_awards`
  ADD CONSTRAINT `user_awards_ibfk_1` FOREIGN KEY (`award_id`) REFERENCES `awards` (`id`) ON DELETE CASCADE;

COMMIT;