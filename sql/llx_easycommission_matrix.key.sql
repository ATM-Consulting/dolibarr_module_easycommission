ALTER TABLE  llx_easycommission_matrix ADD CONSTRAINT fk_user_easycommissionmatrix FOREIGN KEY (fk_user) REFERENCES llx_user(rowid);