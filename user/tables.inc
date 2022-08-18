<?php

if(isset($sql_databaseEngine)){
  if($sql_databaseEngine == 'pgsql'){
    #These are specific Postgres database engine table creation commands

    # Create all of these by hand
    $create_forum_options = "
      CREATE TYPE forumOptions AS ENUM('Read','PostThread','PostReply','PostEdit','OffTopic',
        'Searchable','LoginToRead','ExternallySearchable');
        ";

    // FIXME:
    // THIS WAS A SET AND WILL NOT WORK, NEED TO MAP TO ARRAY
    // options forumOptions[] not null default '{Read,PostThread,PostReply,PostEdit,Searchable}',
    $create_forums_table = "
      create table if not exists f_forums (
        fid serial not null,
        name varchar(150) not null,
        shortname varchar(20) not null,
        options varchar(100) not null default 'Read,PostThread,PostReply,PostEdit,Searchable',
        primary key (fid)
      )";

    $create_unique_types = "
      CREATE TYPE uniqueTypes AS ENUM('Message','Thread');
      ";

    # Unused
    $create_unique_table = "
      create table if not exists f_unique (
        fid int not null,
        type uniqueTypes not null,
        id int not null,
        primary key (fid, type, id)
      )";

    $create_tracking_options = "
      CREATE TYPE trackingOptions AS ENUM('SendEmail', '');
      ";

    // THIS SET SOULD BE ABLE TO BECOME AN ENUM
    $create_tracking_table = "
      create table if not exists f_tracking (
        fid int not null,
        tid int not null,
        aid int not null,
        tstamp timestamp,
        options trackingOptions not null,
        unique (fid, tid, aid)
      )";

    $create_index_f_tracking_fid= "
      create index if not exists f_tracking_fid on f_tracking (fid, aid);";

    # f_messages*, f_threads*, f_sticky*, and associated trigger are create automatically when you create a form
    #    pmid int not null,
    $create_message_flags = "
    CREATE TYPE messageFlags AS ENUM('NewStyle','NoText','Link','Picture','Video','StateLocked');";
    $create_message_states = "
    CREATE TYPE messageState AS ENUM('Active','Moderated','OffTopic','Deleted');";
    // STATE WAS AN ENUM AND SHOULD WORK
    // FLAGS WILL NOT WORK, TEMP CONVERTED TO NVARCHAR
    //         flags messageFlags[],
    $create_message_table = "
      create table if not exists f_messages%d (
        mid SERIAL not null,
        pid int not null,
        tid int not null,
        aid int not null,
        state messageState not null default 'Active',
        flags varchar(100) not null default '',
        name varchar(50) not null,
        email varchar(50) not null,
        date TIMESTAMP not null default CURRENT_TIMESTAMP,
        ip varchar(47) not null default '0.0.0.0',
        subject text not null,
        message text not null,
        url varchar(250) not null,
        urltext varchar(250) not null,
        video varchar(250) not null,
        views int not null,
        changes text not null,
        primary key (mid)
      )";

      $create_index_messages= "
        create index if not exists f_messages%dindex on f_messages%d (pid, tid, state, date);";

      //FIXME: no fill text indexing 
      $create_index_messages_fulltext= "
        NULL;";

      $create_thread_flags = "
        CREATE TYPE threadFlags AS ENUM('Locked','Sticky', ' ');";

      //FIXME: flags WAS A SET AND WILL NOT WORK
      //          flags threadFlags default ' ',
      $create_thread_table = "
        create table if not exists f_threads%d (
          tid serial not null,
          mid int not null default 0,
          replies int not null default 0,
          flags varchar(50) not null default '',
          tstamp TIMESTAMP not null default CURRENT_TIMESTAMP,
          primary key (tid)
        )";

    $create_sticky_table = "
      create table if not exists f_sticky%d (
        sid serial NOT NULL PRIMARY KEY,
        tid int not null,
        mid int not null
      )";

    $create_index_table = "
      create table if not exists f_indexes (
        iid serial not null,
        fid int not null,
        minmid int not null,
        maxmid int not null,
        mintid int not null,
        maxtid int not null,
        active int not null,
        moderated int not null,
        deleted int not null,
        offtopic int not null,
        primary key (iid)
      )";

    $create_index_f_indexes_fid = "
      create index if not exists f_indexes_fid on f_indexes (fid);
      ";

    $create_users_status = "
      CREATE TYPE userStatus AS ENUM('Create','Active','Suspended');";
    $create_users_style = "
      CREATE TYPE userStyle AS ENUM('Classic','Simple','UBB');";
    $create_users_preferences = "
      CREATE TYPE userPreferences AS ENUM('ShowOffTopic','ShowModerated','Collapsed','SecretEmail',
      'FlatThread','SimpleHTML','AutoTrack','HideSignatures','AutoUpdateTracking','OldestFirst',
      'SortbyActive','CollapseOffTopic','RelativeTimestamps');";

    // STATUS WAS ENUM AND SHOULD WORK
    // STYLE WAS ENUM AND SHOULD WORK
    // preferences WILL NOT WORK
    $create_users_table = "
      create table if not exists u_users (
        aid serial not null,
        name varchar(50) not null,
        shortname varchar(50) not null,
        email varchar(100) not null,
        password varchar(100) not null,
        status userStatus not null default 'Create',
        createdate TIMESTAMP not null default CURRENT_TIMESTAMP,
        createip varchar(47),
        cookie varchar(50) not null,
        timezone varchar(20) not null default '',
        style userStyle not null default 'Classic',
        preferences userPreferences[] default null,
        signature text not null,
        threadsperpage int not null default '0',
        posts int not null default '0',
        gmsgfilter bigint not null default '0',
        primary key (aid),
        unique (shortname, email, cookie)
      )";

    $create_moderators_capabilities = "
      CREATE TYPE moderatorsCapabilities AS ENUM('Lock','Moderate','Delete','OffTopic','Advertise');";

    //FIX ME THIS IS A SET AND WILL NOT WORK
    $create_moderators_table = "
      create table if not exists f_moderators (
        aid int not null,
        fid int not null,
        capabilities moderatorsCapabilities[] not null,
        unique (aid, fid)
      )";

    $create_pending_type = "
      CREATE TYPE pendingType AS ENUM('NewAccount','ChangeEmail','ForgotPassword');";

    $create_pending_status = "
      CREATE TYPE pendingStatus AS ENUM('Sent','Bounce','Done');";

    $create_index_u_pending_aid= "
      create index if not exists u_pending_aid on u_pending (aid);
      ";
    // THIS SHOULD WORK
    $create_pending_table = "
      create table if not exists u_pending (
        tid int not null,
        aid int not null,
        cookie varchar(15) not null,
        type pendingType not null,
        data bytea,
        tstamp timestamp,
        status pendingStatus not null,
        primary key (tid),
        unique (cookie)
      )";

    $create_user_preferences_table = "
      create table if not exists u_user_preferences (
        id serial not null,
        aid int not null,
        preference_id int not null,
        preference_value varchar(255),
        primary key (id),
        unique (aid, preference_id)
      )";

    $create_upostcount_status = "
        CREATE TYPE upostcountStatus AS ENUM('Active','Moderated','Deleted','OffTopic');";

    // THIS SHOULD WORK
    $create_upostcount_table = "
      create table if not exists f_upostcount (
        aid int not null,
        fid int not null,
        status upostcountStatus not null,
        count int not null,
        primary key (aid, fid, status)
      )";

    $create_global_messages_state = "
      CREATE TYPE globalMessagesState AS ENUM('Active','Inactive');";

    // THIS SHOULD WORK
    $create_global_messages_table = "
      create table if not exists f_global_messages (
        gid int not null,
        subject text not null,
        url varchar(200) not null,
        name varchar(50) not null,
        date TIMESTAMP not null default CURRENT_TIMESTAMP,
        state globalMessagesState not null default 'Inactive',
        primary key (gid)
      )";

    # ACL - IP bans.
    $create_acl_ips_table = "
      create table if not exists acl_ips (
        id bigserial NOT NULL,
        ip inet NOT NULL,
        mask inet NOT NULL,
        proxy_type INT,
        note VARCHAR(255),
        update_time TIMESTAMP,
        PRIMARY KEY (id)
      )";

    $create_ip_mask_idx = "
      create index if not exists ip_mask_idx on acl_ips (ip, mask);";

    $create_acl_ip_bans_table = "
      create table if not exists acl_ip_bans (
        id bigserial NOT NULL,
        ip_id INT NOT NULL,
        ban_type_id INT NOT NULL,
        PRIMARY KEY (id),
        UNIQUE (ip_id, ban_type_id)
      )";

    # This table should only contain one row - the current
    # schema version.
    $create_schema_version_table = "
      create table if not exists schema_version (
        integrity_keeper bool PRIMARY KEY DEFAULT TRUE,
        version varchar(14) not null,
        CONSTRAINT integrity_keeper CHECK (integrity_keeper)
      )";

    $set_current_schema_version = "
      insert into schema_version
        (version)
      values
        (?)
      ON CONFLICT (integrity_keeper) DO UPDATE 
        SET version = excluded.version;
      ";

    $create_dupposts_table = "
      create table if not exists f_dupposts (
        fid int not null,
        cookie varchar(40) not null,
        mid int not null,
        aid int not null,
        ip varchar(47) not null,
        tstamp TIMESTAMP not null default CURRENT_TIMESTAMP,
        primary key (cookie, aid, ip)
      )";

    $create_offtopic_table_fid_idx = "
      create index if not exists fid_idx on f_offtopic (fid);";

    $create_offtopic_table = "
      create table if not exists f_offtopic (
        fid int not null,
        mid int not null,
        aid int not null,
        tstamp timestamp
      )";

    $insert_static_preferences = "
      insert into u_preferences
        (id, name)
      values
        (1, 'ShowOffTopic'),
        (2, 'ShowModerated'),
        (3, 'Collapsed'),
        (4, 'SecretEmail'),
        (5, 'FlatThread'),
        (6, 'SimpleHTML'),
        (7, 'AutoTrack'),
        (8, 'HideSignatures'),
        (9, 'AutoUpdateTracking'),
        (10, 'OldestFirst'),
        (11, 'SortbyActive'),
        (12, 'CollapseOffTopic'),
        (13, 'ThreadsPerPage'),
        (14, 'Style')
      ON CONFLICT (id) DO UPDATE 
        SET name = excluded.name;
      ";

    $insert_acl_proxy_types = "
      insert into acl_proxy_types
        (id, proxy_type)
      VALUES
        (1, 'TOR')
      ON CONFLICT (id) DO UPDATE 
        SET proxy_type = excluded.proxy_type;";

    $create_acl_proxy_types_table = "
      create table if not exists acl_proxy_types (
        id INT NOT NULL,
        proxy_type VARCHAR(50) NOT NULL,
        PRIMARY KEY (id),
        UNIQUE (proxy_type)
      )";

    $create_acl_ban_types_table = "
      create table if not exists acl_ban_types (
        id INT NOT NULL,
        ban_type VARCHAR(50) NOT NULL,
        PRIMARY KEY (id),
        UNIQUE (ban_type)
      )";

    $insert_static_ban_types = "
      insert into acl_ban_types
        (id, ban_type)
      VALUES
        (1, 'account_creation'),
        (2, 'posts'),
        (3, 'login'),
        (4, 'all')
      ON CONFLICT (id) DO UPDATE 
        SET ban_type = excluded.ban_type;";

    $create_sticky_trigger_function = "
        CREATE OR REPLACE FUNCTION update_sticky%d_function() 
          RETURNS TRIGGER 
          LANGUAGE PLPGSQL
        AS $$
        BEGIN
          if new.flags like '%%Sticky%%' then 
            insert into f_sticky%d(tid, mid) values (new.tid, new.mid);
          else 
            delete from f_sticky%d where tid = new.tid;     
          end if; 
        RETURN NULL;
        END;
        $$;";

    $create_sticky_trigger = "
      create trigger trigger_f%d_sticky_update
      after update on f_threads%d
      for each row
      execute procedure update_sticky%d_function();";

    //create postgresql function to return the unixtimestamp of a timestamp
    $create_unixtimestamp_function = "
      CREATE FUNCTION UNIX_TIMESTAMP(timestamp without time zone) RETURNS bigint
        AS 'select extract(epoch from $1)'
        LANGUAGE SQL
        IMMUTABLE
        RETURNS NULL ON NULL INPUT;";

    //create postgresql function to return the unixtimestamp of a timestamp
    $update_f_indexes_iid_maxmid_function = "
      CREATE OR REPLACE FUNCTION update_f_indexes_iid%d_maxmid_function() 
        RETURNS TRIGGER 
        LANGUAGE PLPGSQL
      AS $$
      BEGIN
          update f_indexes set maxmid = new.mid
          where iid = %d;
      RETURN NULL;
      END;
      $$;";
      
    $trigger_update_f_indexes_iid_mid = "
      create trigger trigger_update_f_indexes_iid%d_mid
      after insert on f_messages%d
      for each row
      execute procedure update_f_indexes_iid%d_maxmid_function();";
      
    $update_f_indexes_iid_maxtid_function = "
      CREATE OR REPLACE FUNCTION update_f_indexes_iid%d_maxtid_function() 
        RETURNS TRIGGER 
        LANGUAGE PLPGSQL
      AS $$
      BEGIN
          update f_indexes set maxtid = new.tid, active = active + 1
          where iid = %d;
      RETURN NULL;
      END;
      $$;";
      
    $trigger_update_f_indexes_iid_tid = "
      create trigger trigger_update_f_indexes_iid%d_tid
      after insert on f_threads%d
      for each row
      execute procedure update_f_indexes_iid%d_maxtid_function();";

   }
  } else {
    #The default database engine is MySQL, these are specific MySQL table creation commands

    # f_messages*, f_threads*, f_sticky*, and associated trigger are create automatically when you create a form
    #    pmid int not null,
    $create_message_table = "
      create table if not exists f_messages%d (
        mid int not null auto_increment,
        pid int not null,
        tid int not null,
        aid int not null,
        state enum('Active','Moderated','OffTopic','Deleted') not null default 'Active',
        flags set('NewStyle','NoText','Link','Picture','Video','StateLocked') not null,
        name varchar(50) not null,
        email varchar(50) not null,
        date datetime not null default CURRENT_TIMESTAMP,
        ip varchar(47) not null default '0.0.0.0',
        subject text not null,
        message text not null,
        url varchar(250) not null,
        urltext varchar(250) not null,
        video varchar(250) not null,
        views int not null,
        changes text not null,
        primary key (mid),
        fulltext (subject,message),
        index (pid),
        index (tid),
        index (state),
        index (date)
      )";

    $create_thread_table = "
      create table if not exists f_threads%d (
        tid int not null auto_increment,
        mid int not null default 0,
        replies int not null default 0,
        flags set('Locked','Sticky') not null,
        tstamp datetime not null default CURRENT_TIMESTAMP,
        primary key (tid),
        index (mid)
      )";

    $create_sticky_table = "
      create table if not exists f_sticky%d (
        sid int NOT NULL PRIMARY KEY AUTO_INCREMENT COMMENT 'Primary Key',
        tid int not null,
        mid int not null
      )";

    $create_index_table = "
      create table if not exists f_indexes (
        iid int not null auto_increment,
        fid int not null,
        minmid int not null,
        maxmid int not null,
        mintid int not null,
        maxtid int not null,
        active int not null,
        moderated int not null,
        deleted int not null,
        offtopic int not null,
        primary key (iid),
        key (fid)
      )";

    $create_forums_table = "
      create table if not exists f_forums (
        fid int not null auto_increment,
        name varchar(150) not null,
        shortname varchar(20) not null,
        options set('Read','PostThread','PostReply','PostEdit','OffTopic','Searchable','LoginToRead','ExternallySearchable') not null default 'Read,PostThread,PostReply,PostEdit,Searchable',
        primary key (fid)
      )";

    $create_users_table = "
      create table if not exists u_users (
        aid int not null auto_increment,
        name varchar(50) not null,
        shortname varchar(50) not null,
        email varchar(100) not null,
        password varchar(100) not null,
        status enum('Create','Active','Suspended') not null default 'Create',
        createdate datetime not null default CURRENT_TIMESTAMP,
        createip varchar(47),
        cookie varchar(50) not null,
        timezone varchar(20) not null default '',
        style enum('Classic','Simple','UBB') not null default 'Classic',
        preferences set('ShowOffTopic','ShowModerated','Collapsed','SecretEmail','FlatThread','SimpleHTML','AutoTrack','HideSignatures','AutoUpdateTracking','OldestFirst','SortbyActive','CollapseOffTopic','RelativeTimestamps') not null default '',
        signature text not null,
        threadsperpage int not null default '0',
        posts int not null default '0',
        gmsgfilter bigint not null default '0',
        primary key (aid),
        unique (shortname),
        unique (email),
        unique (cookie)
      )";

    $create_user_preferences_table = "
      create table if not exists u_user_preferences (
        id int not null auto_increment,
        aid int not null,
        preference_id int not null,
        preference_value varchar(255),
        primary key (id),
        unique key (aid, preference_id)
      )";

    # ACL - IP bans.
    $create_acl_ips_table = "
      create table if not exists acl_ips (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        ip INT UNSIGNED NOT NULL,
        mask INT UNSIGNED NOT NULL,
        proxy_type INT,
        note VARCHAR(255),
        update_time TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY ip_mask_idx (ip, mask)
      )";

    $create_acl_ip_bans_table = "
      create table if not exists acl_ip_bans (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        ip_id INT NOT NULL,
        ban_type_id INT NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY (ip_id, ban_type_id)
      )";

    $create_pending_table = "
      create table if not exists u_pending (
        tid int not null,
        aid int not null,
        cookie varchar(15) not null,
        type enum('NewAccount','ChangeEmail','ForgotPassword') not null,
        data blob,
        tstamp timestamp,
        status enum('Sent','Bounce','Done') not null,
        primary key (tid),
        key (aid),
        unique key (cookie)
      )";

    $create_unique_table = "
      create table if not exists f_unique (
        fid int not null,
        type enum('Message','Thread') not null,
        id int not null,
        primary key (fid, type, id)
      )";

    $create_tracking_table = "
      create table if not exists f_tracking (
        fid int not null,
        tid int not null,
        aid int not null,
        tstamp timestamp,
        options set('SendEmail') not null,
        unique key tid (fid, tid, aid),
      )";

    $create_moderators_table = "
      create table if not exists f_moderators (
        aid int not null,
        fid int not null,
        capabilities set('Lock','Moderate','Delete','OffTopic','Advertise') not null,
        unique key (aid, fid)
      )";

    $create_upostcount_table = "
      create table if not exists f_upostcount (
        aid int not null,
        fid int not null,
        status enum ('Active','Moderated','Deleted','OffTopic') not null,
        count int not null,
        primary key (aid, fid, status)
      )";

    $create_global_messages_table = "
      create table if not exists f_global_messages (
        gid int not null,
        subject text not null,
        url varchar(200) not null,
        name varchar(50) not null,
        date datetime not null default CURRENT_TIMESTAMP,
        state enum('Active','Inactive') not null default 'Inactive',
        primary key (gid)
      )";

    # This table should only contain one row - the current
    # schema version.
    $create_schema_version_table = "
      create table if not exists schema_version (
        integrity_keeper ENUM('') NOT NULL PRIMARY KEY,
        version varchar(14) not null
      )";

    $set_current_schema_version = "
      insert into schema_version
        (version)
      values
        (?)
      on duplicate key update
        version = version 
      ";

    $create_dupposts_table = "
      create table if not exists f_dupposts (
        fid int not null,
        cookie varchar(40) not null,
        mid int not null,
        aid int not null,
        ip varchar(47) not null,
        tstamp datetime not null default CURRENT_TIMESTAMP,
        primary key (cookie, aid, ip)
      )";

    $create_offtopic_table = "
      create table if not exists f_offtopic (
        fid int not null,
        mid int not null,
        aid int not null,
        tstamp timestamp,
        key (fid)
      )";

    $insert_static_preferences = "
      replace into u_preferences
        (id, name)
      values
        (1, 'ShowOffTopic'),
        (2, 'ShowModerated'),
        (3, 'Collapsed'),
        (4, 'SecretEmail'),
        (5, 'FlatThread'),
        (6, 'SimpleHTML'),
        (7, 'AutoTrack'),
        (8, 'HideSignatures'),
        (9, 'AutoUpdateTracking'),
        (10, 'OldestFirst'),
        (11, 'SortbyActive'),
        (12, 'CollapseOffTopic'),
        (13, 'ThreadsPerPage'),
        (14, 'Style')
      ";

    $insert_acl_proxy_types = "
      replace into acl_proxy_types
        (id, proxy_type)
      VALUES
        (1, 'TOR')";

    $create_acl_proxy_types_table = "
      create table if not exists acl_proxy_types (
        id INT NOT NULL,
        proxy_type VARCHAR(50) NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY (proxy_type)
      )";

    $create_acl_ban_types_table = "
      create table if not exists acl_ban_types (
        id INT NOT NULL,
        ban_type VARCHAR(50) NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY (ban_type)
      )";

    $insert_static_ban_types = "
      replace into acl_ban_types
        (id, ban_type)
      VALUES
        (1, 'account_creation'),
        (2, 'posts'),
        (3, 'login'),
        (4, 'all')";

    $create_sticky_trigger = "
      create trigger trigger_f%d_sticky_update
      after update on f_threads%d
      for each row
      begin
        if new.flags like '%%STICKY%%' then 
          insert into f_sticky%d(tid, mid) values (new.tid, new.mid);
        else 
          delete from f_sticky%d where tid = new.tid;     
      end if; 
      end
      ";

}

# Unused
$create_visits_table = "
  create table if not exists f_visits (
    ip varchar(47) not null,
    aid int not null,
    tstamp timestamp,
    primary key (ip, aid)
  )";

# Unused
$create_update_table = "
  create table if not exists f_updates (
    fid int not null,
    mid int not null,
    primary key (fid)
  )";

$create_preferences_table = "
  create table if not exists u_preferences (
    id int not null,
    name varchar(50) not null,
    primary key (id)
  )";

  //FIXME: Add Triggers for index updates

?>
