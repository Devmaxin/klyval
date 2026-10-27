-- Klyval: Supabase Storage bucket for uploaded media. Public read, service-key writes only.
insert into storage.buckets (id, name, public, file_size_limit, allowed_mime_types)
values ('media','media', true, 4194304, array['image/jpeg','image/png','image/webp'])
on conflict (id) do nothing;
