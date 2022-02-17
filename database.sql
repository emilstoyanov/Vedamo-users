CREATE TABLE IF NOT EXISTS `countries` (
  `c_id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(40) NOT NULL DEFAULT '',
  PRIMARY KEY (`c_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=0;

CREATE TABLE IF NOT EXISTS `users` (
  `u_id` int unsigned NOT NULL AUTO_INCREMENT,
  `first` VARCHAR(40) NOT NULL DEFAULT '',
  `last` VARCHAR(40) NOT NULL DEFAULT '',
  `email` VARCHAR(50) NOT NULL DEFAULT '',
  `c_id` int unsigned NOT NULL,
  PRIMARY KEY (`u_id`),
  FOREIGN KEY (`c_id`) REFERENCES `countries` (`c_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=0;

insert into `countries` (name) values("Argentina"),("Australia"),("Austria"),("Belgium"),("Brazil"),("Bulgaria"),("Canada"),("Chile"),("Colombia"),("Denmark"),("Egypt"),("Estonia"),("Finland"),("France"),("Germany"),("Greece"),("Hungary"),("India"),("Indonesia"),("Italy");

ALTER TABLE `users` ADD FULLTEXT KEY `n` (`first`,`last`,`email`);