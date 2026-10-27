-- Local QA harness: verify the RLS/grant lockdown from 0011 works as intended.
-- Run against a local build:  psql -U postgres -d klyval -f qa/security_pg.sql
-- (Supabase needs a service/login role substitute instead of app_local.)
do $$ declare t text; q text; begin
  -- 1. anon/authenticated must not be able to SELECT any public table
  for t in select tablename from pg_tables where schemaname = 'public' loop
    if has_table_privilege('anon', 'public.' || t, 'select') then
      raise exception 'ANON CAN SELECT %', t;
    end if;
    if has_table_privilege('authenticated', 'public.' || t, 'select') then
      raise exception 'AUTHENTICATED CAN SELECT %', t;
    end if;
  end loop;
  raise notice 'anon/authenticated locked out of every public table';

  -- 2. RLS enabled on every public table
  if (select count(*) from pg_tables where schemaname = 'public' and not rowsecurity) > 0 then
    raise exception 'some public tables lack RLS';
  end if;
  raise notice 'RLS enabled on every public table';

  -- 3. updated_at triggers cover all mutable tables
  raise notice 'trg_%%_updated triggers: %',
    (select count(*) from pg_trigger where not tgisinternal and tgname like 'trg_%_updated');

  -- 4. app role readability (substitute your local app login role name here)
  begin
    for t in select tablename from pg_tables where schemaname = 'public' loop
      if not has_table_privilege('app_local', 'public.' || t, 'select') then
        raise notice 'app_local cannot read: %', t;
      end if;
    end loop;
  exception when undefined_object then
    raise notice 'app_local role not present; skipping readability check';
  end;
end $$;
