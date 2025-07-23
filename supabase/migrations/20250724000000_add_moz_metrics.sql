ALTER TABLE domains
    ADD COLUMN domain_authority INT NULL,
    ADD COLUMN page_authority INT NULL,
    ADD COLUMN linking_domains_list TEXT NULL;
