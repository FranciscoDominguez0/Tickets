-- Permite al usuario ver tickets de colegas en sus organizaciones (portal cliente)
ALTER TABLE users
    ADD COLUMN org_tickets_view TINYINT(1) NOT NULL DEFAULT 0
    COMMENT '1 = puede explorar tickets por organización en el portal';
