-- Klyval: Supabase Storage bucket for uploaded media. Public read, service-key writes only.
-- Skips safely on plain Postgres where the storage schema does not exist.
do $$ begin
  if exists (select 1 from information_schema.schemata where schema_name = 'storage') then
    insert into storage.buckets (id, name, public, file_size_limit, allowed_mime_types)
    values ('media','media', true, 4194304, array['image/jpeg','image/png','image/webp'])
    on conflict (id) do nothing;
  end if;
end $$;
