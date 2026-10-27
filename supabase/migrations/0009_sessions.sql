-- Klyval: server-side sessions, shared by all container instances.
create table public.sessions (
  id         varchar(128) primary key,
  data       text not null,
  expires_at timestamptz not null);
create index idx_sessions_expires on public.sessions (expires_at);
