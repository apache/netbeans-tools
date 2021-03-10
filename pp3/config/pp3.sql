-- Licensed to the Apache Software Foundation (ASF) under one
-- or more contributor license agreements.  See the NOTICE file
-- distributed with this work for additional information
-- regarding copyright ownership.  The ASF licenses this file
-- to you under the Apache License, Version 2.0 (the
-- "License"); you may not use this file except in compliance
-- with the License.  You may obtain a copy of the License at
--
--   https://www.apache.org/licenses/LICENSE-2.0
--
-- Unless required by applicable law or agreed to in writing,
-- software distributed under the License is distributed on an
-- "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
-- KIND, either express or implied.  See the License for the
-- specific language governing permissions and limitations
-- under the License.


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

CREATE TABLE IF NOT EXISTS `user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idp_provider_id` varchar(20) COLLATE utf8_czech_ci NOT NULL,
  `idp_user_id` varchar(100) COLLATE utf8_czech_ci NOT NULL,
  `email` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `name` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `admin` boolean DEFAULT false NOT NULL,
  `verifier` boolean DEFAULT false NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY(idp_provider_id, idp_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

CREATE TABLE IF NOT EXISTS `category` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

CREATE TABLE IF NOT EXISTS `nb_version` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `version` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `verifiable` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

CREATE TABLE IF NOT EXISTS `plugin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `artifactid` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  `license` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  `author_id` int(11) NOT NULL REFERENCES user(id),
  `added_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_updated_at` datetime DEFAULT CURRENT_TIMESTAMP,
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

CREATE TABLE IF NOT EXISTS `plugin_version` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `version` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `url` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  `relnotes` text COLLATE utf8_czech_ci,
  `plugin_id` int(11) NOT NULL REFERENCES plugin(id),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

CREATE TABLE IF NOT EXISTS `verification` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `status` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `plugin_version_id` int(11) NOT NULL REFERENCES plugin_version(id),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;


CREATE TABLE IF NOT EXISTS `nb_version_plugin_version` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nb_version_id` int(11) NOT NULL REFERENCES nb_version(id),
  `plugin_version_id` int(11) NOT NULL REFERENCES plugin_version(id),
  `verification_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;


CREATE TABLE IF NOT EXISTS `plugin_category` (
  `plugin_id` int(11) NOT NULL REFERENCES plugin(id),
  `category_id` int(11) NOT NULL REFERENCES category(id),
  PRIMARY KEY (`plugin_id`,`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;


CREATE TABLE IF NOT EXISTS `verification_request` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `vote` int(11) DEFAULT NULL,
  `voted_at` datetime DEFAULT NULL,
  `comment` text COLLATE utf8_czech_ci,
  `verification_id` int(11) DEFAULT NULL,
  `verifier_id` int(11) NOT NULL REFERENCES user(id),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

CREATE TABLE IF NOT EXISTS `plugin_version_digest` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `plugin_version_id` int(11) NOT NULL REFERENCES plugin_version(id),
  `algorithm` varchar(50) NOT NULL,
  `value` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

COMMIT;

ALTER TABLE plugin_version ADD COLUMN info_xml mediumblob DEFAULT NULL;
ALTER TABLE plugin_version ADD COLUMN artifact_size integer DEFAULT NULL;
ALTER TABLE nb_version ADD COLUMN catalog_rebuild_requested datetime DEFAULT NULL;
ALTER TABLE nb_version ADD COLUMN catalog_rebuild datetime DEFAULT NULL;
ALTER TABLE plugin_version ADD COLUMN error_message text COLLATE utf8_czech_ci DEFAULT NULL;

CREATE TABLE `plugin_user` (
    plugin_id int(11) NOT NULL REFERENCES plugin(id),
    user_id int(11) NOT NULL REFERENCES user(id),
    PRIMARY KEY (`plugin_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

insert into plugin_user SELECT id, author_id FROM plugin;

ALTER TABLE plugin DROP COLUMN author_id;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
