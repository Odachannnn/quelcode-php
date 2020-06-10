CREATE TABLE `members` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(255) NOT NULL,
      `email` varchar(255) NOT NULL,
      `password` varchar(100) NOT NULL,
      `picture` varchar(255) NOT NULL,
      `created` datetime NOT NULL,
      `modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

CREATE TABLE `posts` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `message` text NOT NULL,
      `member_id` int(11) NOT NULL,
      `retwi_member_id` int(11) NOT NULL,
      `reply_post_id` int(11) NOT NULL,
      `retwi_origin_id` int(11) NOT NULL,
      `created` datetime NOT NULL,
      `modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

CREATE TABLE `prechallenge3` (
      `value` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

INSERT INTO `prechallenge3` (`value`) VALUES (1),(17),(3),(13),(11),(7),(19),(5);

CREATE TABLE `likes` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `like_member_id` int(11) NOT NULL,
      `like_post_id` int(11) NOT NULL,
      `created` datetime NOT NULL,
      PRIMARY KEY (`id`)
 ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

CREATE TABLE `reposts` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `my_member_id` int(11) NOT NULL,
      `origin_member_id` int(11) NOT NULL,
      `origin_id` int(11) NOT NULL,
      `created` datetime NOT NULL,
      PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;
