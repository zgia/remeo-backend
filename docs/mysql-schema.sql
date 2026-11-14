
DROP TABLE IF EXISTS `category`;
CREATE TABLE `category` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `parentid` int unsigned NOT NULL DEFAULT '0',
    `ckey` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    `title` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `hospday`;
CREATE TABLE `hospday` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `patientid` int unsigned NOT NULL DEFAULT '0' COMMENT '患者',
    `hospseq` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '第几次住院',
    `inday` date DEFAULT NULL COMMENT '住院日期',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `hospital`;
CREATE TABLE `hospital` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `title` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '医院名称',
    `createdat` int unsigned NOT NULL DEFAULT '0',
    `updatedat` int unsigned NOT NULL DEFAULT '0',
    `deletedat` int unsigned NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `log`;
CREATE TABLE `log` (
    `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键 日志ID',
    `type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '日志操作类型',
    `objectid` int unsigned NOT NULL DEFAULT '0' COMMENT '操作对象ID',
    `script` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '脚本名称',
    `action` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '执行操作',
    `ipaddress` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'IP地址',
    `userid` int unsigned NOT NULL DEFAULT '0' COMMENT '外键 用户ID',
    `fromcontent` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '修改前的内容',
    `tocontent` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '修改后的内容',
    `createdat` int unsigned NOT NULL DEFAULT '0' COMMENT '操作时间',
    PRIMARY KEY (`id`),
    KEY `log_objecttitle` (`type`),
    KEY `log_objectid` (`objectid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `media`;
CREATE TABLE `media` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `treatmentid` int unsigned NOT NULL DEFAULT '0',
    `patientid` int unsigned NOT NULL DEFAULT '0',
    `file` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '文件',
    `thumb` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '缩略图',
    `mdesc` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '描述',
    `deleted` tinyint unsigned NOT NULL DEFAULT '0',
    `createdat` int unsigned NOT NULL DEFAULT '0',
    `updatedat` int unsigned NOT NULL DEFAULT '0',
    `deletedat` int unsigned NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    KEY `media_idx_patient` (`patientid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `migrations`;
CREATE TABLE `migrations` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `batch` int NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `pathology`;
CREATE TABLE `pathology` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `patientid` int unsigned NOT NULL DEFAULT '0',
    `histopathology` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '组织病理',
    `who` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'WHO分级',
    `tertc228t` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'tertc228t',
    `tertc250t` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'tertc250t',
    `cdkn2a` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'cdkn2a',
    `cdkn2b` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'cdkn2b',
    `pten` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'pten',
    `smarcb1` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'smarcb1',
    `pik3ca` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'pik3ca',
    `ki67` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Ki-67',
    `h3k27m` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'h3k27m',
    `pydesc` text COLLATE utf8mb4_unicode_ci COMMENT '描述',
    `pyday` date DEFAULT NULL COMMENT '病理日期',
    `pycode` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '病理号',
    `hospseq` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '住院次数',
    `createdat` int unsigned NOT NULL DEFAULT '0',
    `updatedat` int unsigned NOT NULL DEFAULT '0',
    `deletedat` int unsigned NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    KEY `pathology_idx_patient` (`patientid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `patient`;
CREATE TABLE `patient` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `doctorid` int unsigned NOT NULL DEFAULT '0' COMMENT '属于哪个医生',
    `hospitalid` int unsigned NOT NULL DEFAULT '0' COMMENT '属于哪个医院',
    `age` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '年龄',
    `idcard` varchar(18) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '身份证号码',
    `realname` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '姓名',
    `gender` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '性别，1：男，2：女',
    `mobile` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '联系方式',
    `linkman` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '联系人',
    `linkrel` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '关系',
    `mobile2` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '联系方式',
    `linkrel2` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '关系',
    `linkman2` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '联系人',
    `mobile3` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '联系方式',
    `linkrel3` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '关系',
    `linkman3` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '联系人',
    `firstday` date DEFAULT NULL COMMENT '首诊日期：2022-10-12',
    `nextcheckday` date DEFAULT NULL COMMENT '下次复查日期',
    `lastcheckday` date DEFAULT NULL COMMENT '末次复查日期',
    `lastcheckid` int NOT NULL DEFAULT '0' COMMENT '末次复查的treatment id',
    `checkcount` smallint unsigned NOT NULL DEFAULT '0' COMMENT '复查次数',
    `nextfollowupday` date DEFAULT NULL COMMENT '下次随访日期',
    `lastfollowupday` date DEFAULT NULL COMMENT '末次随访日期',
    `lastfollowupid` int NOT NULL DEFAULT '0' COMMENT '末次随访的treatment id',
    `followupcount` smallint unsigned NOT NULL DEFAULT '0' COMMENT '随访次数',
    `isout` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '是否门诊：1：是，0：否',
    `deletedout` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '是否门诊删除：1：是，0：否',
    `isin` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '是否住院：1：是，0：否',
    `deletedin` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '是否住院删除：1：是，0：否',
    `hospcode` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '住院号',
    `hospday` date DEFAULT NULL COMMENT '住院日期：2022-10-12',
    `hospseq` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '住院次数',
    `surgeryseq` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '手术在第几次住院时进行',
    `hospinitialstate` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '初始状态：1：初发，2：复发',
    `isalive` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '1：存活，2：死亡',
    `recurrence` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '1：未见复发2：（复发）复发/残余病灶增大3：残余病灶稳定4：残余病灶变小',
    `createdat` int unsigned NOT NULL DEFAULT '0',
    `updatedat` int unsigned NOT NULL DEFAULT '0',
    `deletedat` int unsigned NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    KEY `patient_idx_hospdoc` (`hospitalid`,`doctorid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `permission`;
CREATE TABLE `permission` (
    `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
    `groupid` int unsigned NOT NULL DEFAULT '0' COMMENT '权限组',
    `code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '权限Code',
    `title` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '权限名称',
    `createdat` int unsigned NOT NULL DEFAULT '0',
    `updatedat` int unsigned NOT NULL DEFAULT '0',
    `deletedat` int unsigned NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `progress`;
CREATE TABLE `progress` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `patientid` int unsigned NOT NULL COMMENT '病人',
    `firstsituation` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '门诊情况，1：做了，2：没做',
    `firstvideo` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '门诊影像，1：做了，2：没做',
    `hospsituation` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '入院情况，1：做了，2：没做',
    `surgery` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '手术，1：做了，2：没做',
    `vbs` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '术前影像，1：做了，2：没做',
    `vas` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '术后影像，1：做了，2：没做',
    `pathology` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '病理，1：做了，2：没做',
    `discharge` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '出院，1：做了，2：没做',
    PRIMARY KEY (`id`),
    UNIQUE KEY `progress_idx_patient` (`patientid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `radiotherapy`;
CREATE TABLE `radiotherapy` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `treatmentid` int unsigned NOT NULL DEFAULT '0',
    `patientid` int unsigned NOT NULL DEFAULT '0',
    `ryaction` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '是否放疗，1：是，2：否',
    `rysyinterval` smallint unsigned NOT NULL DEFAULT '0' COMMENT '与手术间隔',
    `rycount` smallint unsigned NOT NULL DEFAULT '0' COMMENT '次数',
    `rydoses` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '剂量',
    `hospseq` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '住院次数',
    `createdat` int unsigned NOT NULL DEFAULT '0',
    `updatedat` int unsigned NOT NULL DEFAULT '0',
    `deletedat` int unsigned NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    KEY `radiotherapy_idx_patient` (`patientid`),
    KEY `radiotherapy_idx_treatment` (`treatmentid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `situation`;
CREATE TABLE `situation` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `treatmentid` int unsigned NOT NULL DEFAULT '0' COMMENT '治疗',
    `patientid` int unsigned NOT NULL DEFAULT '0' COMMENT '病人',
    `kps` int unsigned NOT NULL DEFAULT '0' COMMENT 'KPS',
    `cc` varchar(3000) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '主诉（chief complaint）',
    `positive` text COLLATE utf8mb4_unicode_ci COMMENT '阳性体征',
    `epilepsy` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '是否有癫痫发作，1：是，2：否',
    `uterectomy` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '是否行子宫切除术，1：是，2：否',
    `fertility` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '生育情况：G 数字 P 数字',
    `otherdesc` text COLLATE utf8mb4_unicode_ci COMMENT '入院其他情况、出院特殊情况',
    `uterinefibroids` tinyint NOT NULL DEFAULT '0' COMMENT '是否有子宫肌瘤，1：是，2：否',
    `menopause` tinyint NOT NULL DEFAULT '0' COMMENT '是否绝经，1：是，2：否',
    `pastmedicalhistory` text COLLATE utf8mb4_unicode_ci COMMENT '既往史',
    `stype` char(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '类别，首诊f、复查c、入院i、出院d、术后随访u等等',
    `hospseq` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '住院次数',
    `createdat` int unsigned NOT NULL DEFAULT '0',
    `updatedat` int unsigned NOT NULL DEFAULT '0',
    `deletedat` int unsigned NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    KEY `situation_idx_treatment` (`treatmentid`),
    KEY `situation_idx_patient` (`patientid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `smscode`;
CREATE TABLE `smscode` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `userid` int unsigned NOT NULL DEFAULT '0',
    `mobile` char(11) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    `smscode` char(6) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    `createdat` int unsigned NOT NULL DEFAULT '0',
    `smsday` int unsigned NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    KEY `smscode_userid` (`userid`),
    KEY `smscode_idx_mobile` (`mobile`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `smslog`;
CREATE TABLE `smslog` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `userid` int unsigned NOT NULL DEFAULT '0',
    `mobile` varchar(11) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    `msg` varchar(300) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    `issent` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '是否发送',
    `createdat` int unsigned NOT NULL DEFAULT '0',
    `updatedat` int unsigned NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    KEY `smslog_idx_mobile` (`mobile`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `surgery`;
CREATE TABLE `surgery` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `patientid` int unsigned NOT NULL DEFAULT '0',
    `sytype` char(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'y' COMMENT '手术类型',
    `hospseq` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '住院次数',
    `surgeryday` date DEFAULT NULL COMMENT '手术日期',
    `surgeryway` smallint unsigned NOT NULL DEFAULT '0' COMMENT '手术方式，1：全切，2：次全切',
    `surgerydesc` text COLLATE utf8mb4_unicode_ci,
    `bleedingvol` smallint unsigned NOT NULL DEFAULT '0' COMMENT '出血量',
    `alloredcellsursp` smallint unsigned NOT NULL DEFAULT '0' COMMENT '异体悬浮红细胞Allogeneic red cells suspension',
    `alloplasma` smallint unsigned NOT NULL DEFAULT '0' COMMENT '异体血浆Allogeneic plasma',
    `tumorbloodsupply` smallint unsigned NOT NULL DEFAULT '0' COMMENT '肿瘤血供',
    `tumortexture` smallint unsigned NOT NULL DEFAULT '0' COMMENT '肿瘤质地',
    `tumoradhesionlevel` smallint unsigned NOT NULL DEFAULT '0' COMMENT '肿瘤与脑组织的黏连程度',
    `surgeryafter` text COLLATE utf8mb4_unicode_ci COMMENT '术后特殊情况',
    `createdat` int unsigned NOT NULL DEFAULT '0',
    `updatedat` int unsigned NOT NULL DEFAULT '0',
    `deletedat` int unsigned NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    KEY `surgery_idx_patient` (`patientid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `treatment`;
CREATE TABLE `treatment` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `patientid` int unsigned NOT NULL DEFAULT '0' COMMENT '病人',
    `outin` char(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'o：门诊，i：住院',
    `step` smallint unsigned NOT NULL DEFAULT '1' COMMENT '患者治疗次数',
    `checkday` date DEFAULT NULL COMMENT '复查日期',
    `checkway` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '复查方式：来院/电话',
    `checkalive` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '1存活/2死亡',
    `causeofdeath` varchar(256) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '死亡原因',
    `hospseq` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '住院次数',
    `createdat` int unsigned NOT NULL DEFAULT '0',
    `updatedat` int unsigned NOT NULL DEFAULT '0',
    `deletedat` int unsigned NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    KEY `treatment_idx_patient` (`patientid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user`;
CREATE TABLE `user` (
    `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键 医生ID',
    `hospitalid` int unsigned NOT NULL DEFAULT '0' COMMENT '外键 医院ID',
    `username` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '用户名称',
    `realname` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '用户真实名称',
    `mobile` varchar(11) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '手机',
    `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Laravel 密码散列，兼容待升级的旧密码',
    `salt` varchar(6) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '密码salt',
    `lastip` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '最后登录IP地址',
    `lastrealip` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '最后登录真实IP地址',
    `createdat` int unsigned NOT NULL DEFAULT '0',
    `updatedat` int unsigned NOT NULL DEFAULT '0',
    `deletedat` int unsigned NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    KEY `user_idx_hospital` (`hospitalid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `userpermission`;
CREATE TABLE `userpermission` (
    `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
    `doctorid` int unsigned NOT NULL DEFAULT '0' COMMENT '医生ID',
    `permissioncode` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '权限Code',
    `createdat` int unsigned NOT NULL DEFAULT '0',
    `updatedat` int unsigned NOT NULL DEFAULT '0',
    `deletedat` int unsigned NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `video`;
CREATE TABLE `video` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `treatmentid` int unsigned NOT NULL DEFAULT '0' COMMENT '治疗',
    `patientid` int unsigned NOT NULL DEFAULT '0' COMMENT '病人',
    `videoday` date DEFAULT NULL COMMENT '影像日期',
    `videostatus` int unsigned NOT NULL DEFAULT '0' COMMENT '本次复查影像学检查状态：在医院做MR还是外带等等',
    `sinus` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '是否累及窦，1是，2否',
    `ct` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '是否做过CT：2否，3本院，4外院',
    `ctcalc` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'CT有无钙化：1是，2否',
    `sequence` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '序列是否符合要求，1是，2否',
    `lesioncount` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '病灶数量',
    `lesionposition` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '病灶侧别（左/右/双侧/中线）',
    `lesionpart` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0' COMMENT '病灶部位（前颅底/中颅底...）',
    `cemrivol` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'T1+C体积',
    `cemrilength` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'T1+C的长度',
    `cemriwidth` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'T1+C的宽度',
    `cemrithick` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'T1+C的厚度',
    `t2flairvol` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'T2/flair体积',
    `t2flairlength` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'T2/flair的长度',
    `t2flairwidth` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'T2/flair的宽度',
    `t2flairthick` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'T2/flair的厚度',
    `edemavol` smallint NOT NULL DEFAULT '0' COMMENT '水肿体积',
    `suggestion` varchar(3000) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '建议',
    `videoresult` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '影像评估结果，残余病灶的变化等',
    `vtype` char(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '类别，首诊f、复查c、术前i、术后d、随访u等等',
    `hospseq` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '住院次数',
    `createdat` int unsigned NOT NULL DEFAULT '0',
    `updatedat` int unsigned NOT NULL DEFAULT '0',
    `deletedat` int unsigned NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    KEY `video_idx_patient` (`patientid`),
    KEY `video_idx_treatment` (`treatmentid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;