-- Klyval: database access control. The PHP app is the ONLY client; the Supabase Data API exposes NOTHING.
-- NOTE: the password of this role is set by an operator (SQL editor / dashboard), NEVER in a migration file.
do $$ begin
  if not exists (select 1 from pg_roles where rolname = 'klyval_app') then
    create role klyval_app nologin;   -- set login password manually: alter role klyval_app login password '<from secret store>'
  end if;
end $$;

grant usage on schema public to klyval_app;
grant select, insert, update, delete on all tables    in schema public to klyval_app;
grant usage, select                  on all sequences in schema public to klyval_app;

-- Lock the public Data API roles out completely (defense in depth on top of RLS).
-- The two Supabase-managed roles are created only where they don't already exist (local
-- Postgres has neither), so this migration applies cleanly in both environments.
do $$ begin
  if not exists (select 1 from pg_roles where rolname = 'anon') then
    create role anon nologin;
  end if;
  if not exists (select 1 from pg_roles where rolname = 'authenticated') then
    create role authenticated nologin;
  end if;
end $$;
revoke all on all tables    in schema public from anon, authenticated;
revoke all on all sequences in schema public from anon, authenticated;
revoke all on all functions in schema public from anon, authenticated;
alter default privileges in schema public revoke all on tables    from anon, authenticated;
alter default privileges in schema public revoke all on sequences from anon, authenticated;

-- RLS everywhere; anon/authenticated get NO policies at all = deny. klyval_app is allowed explicitly.
do $$ declare t text; begin
  for t in select tablename from pg_tables where schemaname = 'public' and tablename <> 'schema_migrations' loop
    execute format('alter table public.%I enable row level security', t);
    execute format('drop policy if exists app_full_access on public.%I', t);
    execute format('create policy app_full_access on public.%I for all to klyval_app using (true) with check (true)', t);
  end loop;
end $$;
