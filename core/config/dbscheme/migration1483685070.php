<?php

class Migration1483685070 extends AbstractMigration
{
  /**
   * @todo Return action which should run before db modification
   */
  protected function buildPreup() { return array(); }
  /**
   * @todo Return action which should run after db modification
   */
  protected function buildPostup() { return array(); }
  /**
   * @todo Return action which should run before db rollback
   */
  protected function buildPredown() { return array(); }
  /**
   * @todo Return action which should run after db rollback
   */
  protected function buildPostdown() { return array(); }

  protected function buildUp()
  {
    return array(
      "DROP TABLE IF EXISTS `users`",
      "CREATE TABLE `users` (\r"
      . "  `uid` int(11) unsigned NOT NULL AUTO_INCREMENT,\r"
      . "  `username` varchar(50) NOT NULL,\r"
      . "  `password` varchar(255) NOT NULL,\r"
      . "  `role` varchar(50) NOT NULL,\r"
      . "  `crdate` int(11) unsigned NOT NULL DEFAULT '0',\r"
      . "  PRIMARY KEY (`uid`)\r"
      . ") ENGINE=MyISAM DEFAULT CHARSET=utf8",
    );
  }

  protected function buildDown()
  {
    return array(
      "DROP TABLE IF EXISTS `users`",
    );
  }

  protected function getRev() { return 1483685070; }

  protected function getAlias() { return 'slimpd_v2'; }

}
