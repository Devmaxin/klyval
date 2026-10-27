-- Klyval: replace MySQL's ON UPDATE CURRENT_TIMESTAMP. Fires only when a row really changes.
do $$ declare t text; begin
  foreach t in array array['admin_users','site_settings','homepage_settings','contact_submissions','media',
    'projects','team_members','services','homepage_stats','social_links','site_media','articles'] loop
    execute format('create trigger trg_%1$s_updated before update on public.%1$s
      for each row when (old.* is distinct from new.*) execute function public.set_updated_at()', t);
  end loop;
end $$;
