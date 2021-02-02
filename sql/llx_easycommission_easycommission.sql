CREATE TABLE llx_easycommission_matrix
(
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  entity integer DEFAULT 1 NOT NULL,
  discountPercentageFrom float DEFAULT 0,
  discountPercentageTo float DEFAULT 0,
  commissionPercentage float DEFAULT 0,
  tms timestamp
)ENGINE=innodb;