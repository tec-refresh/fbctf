FROM mysql:8.0

COPY database/schema.sql /docker-entrypoint-initdb.d/01-schema.sql
COPY database/countries.sql /docker-entrypoint-initdb.d/02-countries.sql
COPY database/logos.sql /docker-entrypoint-initdb.d/03-logos.sql
COPY database/admin.sql /docker-entrypoint-initdb.d/04-admin.sql
