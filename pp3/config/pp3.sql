-- phpMyAdmin SQL Dump
-- version 4.8.3
-- https://www.phpmyadmin.net/
--
-- Počítač: localhost
-- Vytvořeno: Čtv 10. říj 2019, 13:57
-- Verze serveru: 5.7.27-0ubuntu0.18.04.1
-- Verze PHP: 5.6.40-12+ubuntu18.04.1+deb.sury.org+1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Databáze: `pp3`
--

-- --------------------------------------------------------

--
-- Struktura tabulky `category`
--

CREATE TABLE IF NOT EXISTS `category` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `nb_version`
--

CREATE TABLE IF NOT EXISTS `nb_version` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `version` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `verifiable` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `nb_version_plugin_version`
--

CREATE TABLE IF NOT EXISTS `nb_version_plugin_version` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nb_version_id` int(11) NOT NULL,
  `plugin_version_id` int(11) NOT NULL,
  `verification_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `plugin`
--

CREATE TABLE IF NOT EXISTS `plugin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `artifactid` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  `license` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  `author` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  `added_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `approved_at` datetime DEFAULT NULL,
  `url` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  `status` int(11) DEFAULT NULL,
  `latest_version` varchar(11) COLLATE utf8_czech_ci DEFAULT NULL,
  `release_version` varchar(11) COLLATE utf8_czech_ci DEFAULT NULL,
  `description` text COLLATE utf8_czech_ci,
  `short_description` text COLLATE utf8_czech_ci,
  `image` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  `homepage` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  `downloads` int(11) DEFAULT NULL,
  `groupid` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `plugin_category`
--

CREATE TABLE IF NOT EXISTS `plugin_category` (
  `plugin_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  PRIMARY KEY (`plugin_id`,`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `plugin_version`
--

CREATE TABLE IF NOT EXISTS `plugin_version` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `version` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `url` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  `relnotes` text COLLATE utf8_czech_ci,
  `plugin_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `verification`
--

CREATE TABLE IF NOT EXISTS `verification` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `status` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `plugin_version_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `verification_request`
--

CREATE TABLE IF NOT EXISTS `verification_request` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `vote` int(11) DEFAULT NULL,
  `voted_at` datetime DEFAULT NULL,
  `comment` text COLLATE utf8_czech_ci,
  `verification_id` int(11) DEFAULT NULL,
  `verifier_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `verifier`
--

CREATE TABLE IF NOT EXISTS `verifier` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
