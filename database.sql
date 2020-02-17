--
-- Table structure for table `sugerencias_list`
--

ALTER TABLE `_sugerencias_votes` DROP CONSTRAINT sugerencias_votes_ibfk_1;
DROP TABLE IF EXISTS `_sugerencias_list`;
CREATE TABLE IF NOT EXISTS `_sugerencias_list` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `person_id` bigint(11) NOT NULL,
  `text` varchar(1024) NOT NULL,
  `votes_count` int(6) NOT NULL DEFAULT '0',
  `status` enum('NEW','APPROVED','DISCARDED') NOT NULL DEFAULT 'NEW',
  `limit_votes` int(11) NOT NULL DEFAULT '0',
  `limit_date` timestamp,
  `inserted` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated` timestamp NULL DEFAULT NULL COMMENT 'Date when gets approved or rejected',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `_sugerencias_votes`
--

DROP TABLE IF EXISTS `_sugerencias_votes`;
CREATE TABLE IF NOT EXISTS `_sugerencias_votes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `person_id` bigint(11) NOT NULL,
  `feedback` int(11) NOT NULL,
  `vote_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `feedback` (`feedback`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `_sugerencias_votes`
--
ALTER TABLE `_sugerencias_votes`
  ADD CONSTRAINT `sugerencias_votes_ibfk_1` FOREIGN KEY (`feedback`) REFERENCES `_sugerencias_list` (`id`) ON DELETE CASCADE;
