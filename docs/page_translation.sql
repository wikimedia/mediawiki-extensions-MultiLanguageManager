CREATE TABLE /*_*/page_translation (
  `source` int(8) NOT NULL,
  translate int(8) NOT NULL,
  UNIQUE KEY source_2 (`source`,translate),
  KEY `source` (`source`),
  KEY translate (translate)
)/*$wgDBTableOptions*/;