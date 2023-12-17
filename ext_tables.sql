#
# Table structure for table 'tx_meilisearch_last_searches'
#
CREATE TABLE tx_meilisearch_last_searches
(
	sequence_id tinyint(3) unsigned DEFAULT '0' NOT NULL,
	tstamp      int(11)             DEFAULT '0' NOT NULL,
	keywords    varchar(128)        DEFAULT ''  NOT NULL,

	PRIMARY KEY (sequence_id)
) ENGINE = InnoDB;


#
# Table structure for table 'tx_meilisearch_statistics'
#
CREATE TABLE tx_meilisearch_statistics
(
	uid               int(11)                      NOT NULL auto_increment,
	pid               int(11)          DEFAULT '0' NOT NULL,
	root_pid          int(11)          DEFAULT '0' NOT NULL,
	tstamp            int(11)          DEFAULT '0' NOT NULL,
	language          int(11)          DEFAULT '0' NOT NULL,

	num_found         int(11)          DEFAULT '0' NOT NULL,
	suggestions_shown int(1)           DEFAULT '0' NOT NULL,
	time_total        int(11)          DEFAULT '0' NOT NULL,
	time_preparation  int(11)          DEFAULT '0' NOT NULL,
	time_processing   int(11)          DEFAULT '0' NOT NULL,

	feuser_id         int(11) unsigned DEFAULT '0' NOT NULL,
	ip                varchar(255)     DEFAULT ''  NOT NULL,

	keywords          varchar(128)     DEFAULT ''  NOT NULL,
	page              int(5) unsigned  DEFAULT '0' NOT NULL,
	filters           blob,
	sorting           varchar(128)     DEFAULT ''  NOT NULL,
	parameters        blob,

	PRIMARY KEY (uid),
	KEY rootpid_keywords (root_pid, keywords),
	KEY rootpid_tstamp (root_pid, tstamp)
) ENGINE = InnoDB;


#
# Table structure for table 'tx_meilisearch_indexqueue_item'
#
CREATE TABLE tx_meilisearch_indexqueue_item
(
	uid                     int(11)                  NOT NULL auto_increment,

	root                    int(11)      DEFAULT '0' NOT NULL,

	item_type               varchar(255) DEFAULT ''  NOT NULL,
	item_uid                int(11)      DEFAULT '0' NOT NULL,
	indexing_configuration  varchar(255) DEFAULT ''  NOT NULL,
	has_indexing_properties tinyint(1)   DEFAULT '0' NOT NULL,
	indexing_priority       int(11)      DEFAULT '0' NOT NULL,
	changed                 int(11)      DEFAULT '0' NOT NULL,
	indexed                 int(11)      DEFAULT '0' NOT NULL,
	errors                  text                     NOT NULL,
	pages_mountidentifier   varchar(255) DEFAULT ''  NOT NULL,

	PRIMARY KEY (uid),
	KEY changed (changed),
	KEY root (root),
	KEY indexing_priority_changed (indexing_priority, changed),
	KEY item_id (item_type(191), item_uid),
	KEY site_statistics (root, indexing_configuration),
	KEY pages_mountpoint (item_type(191), item_uid, has_indexing_properties, pages_mountidentifier(191))
) ENGINE = InnoDB;


#
# Table structure for table 'tx_meilisearch_indexqueue_indexing_property'
#
CREATE TABLE tx_meilisearch_indexqueue_indexing_property
(
	uid            int(11)                  NOT NULL auto_increment,

	root           int(11)      DEFAULT '0' NOT NULL,
	item_id        int(11)      DEFAULT '0' NOT NULL,

	property_key   varchar(255) DEFAULT ''  NOT NULL,
	property_value mediumtext               NOT NULL,

	PRIMARY KEY (uid),
	KEY item_id (item_id)
) ENGINE = InnoDB;

#
# Table structure for table 'tx_meilisearch_eventqueue_item'
#
CREATE TABLE tx_meilisearch_eventqueue_item
(
	uid           int(11)                         NOT NULL auto_increment,

	tstamp        int(11)             DEFAULT '0' NOT NULL,
	event         longblob,
	error         tinyint(3) unsigned DEFAULT '0' NOT NULL,
	error_message text,

	PRIMARY KEY (uid),
	KEY tstamp (tstamp),
	KEY error (error),
) ENGINE = InnoDB;

#
# Extending 'pages' table with extra keys
#
CREATE TABLE pages
(
	no_search_sub_entries tinyint(3) unsigned DEFAULT '0' NOT NULL,
	KEY content_from_pid_deleted (content_from_pid, deleted),
	KEY doktype_no_search_deleted (doktype, no_search, deleted)
);
