--
-- Table structure for table `sugerencias_list`
--

DROP TABLE IF EXISTS `sugerencias_list`;
CREATE TABLE IF NOT EXISTS `sugerencias_list` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user` char(100) NOT NULL,
  `text` varchar(1024) NOT NULL,
  `votes_count` int(6) NOT NULL DEFAULT '0',
  `status` enum('NEW','APPROVED','DISCARDED') NOT NULL DEFAULT 'NEW',
  `limit_votes` int(11) NOT NULL DEFAULT '0',
  `limit_date` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `inserted` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated` timestamp NULL DEFAULT NULL COMMENT 'Date when gets approved or rejected',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=4 ;

-- --------------------------------------------------------

--
-- Table structure for table `sugerencias_votes`
--

DROP TABLE IF EXISTS `sugerencias_votes`;
CREATE TABLE IF NOT EXISTS `sugerencias_votes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user` char(100) NOT NULL,
  `feedback` int(11) NOT NULL,
  `vote_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `feedback` (`feedback`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=19 ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `sugerencias_votes`
--
ALTER TABLE `sugerencias_votes`
  ADD CONSTRAINT `sugerencias_votes_ibfk_1` FOREIGN KEY (`feedback`) REFERENCES `sugerencias_list` (`id`) ON DELETE CASCADE;
