<?php
View::section('pages/home/hero', $c);
if ($c['show']['services']) View::section('pages/home/services', $c);
if ($c['show']['work'] && $c['projects']) View::section('pages/home/work', $c);
if ($c['show']['studio']) View::section('pages/home/studio', $c);
if ($c['show']['stats'] && $c['stats']) View::section('pages/home/stats', $c);   // real numbers only; hidden while empty
if ($c['show']['team'] && $c['team']) View::section('pages/home/team', $c);
if ($c['show']['process']) View::section('pages/home/process', $c);
View::section('pages/home/cta', $c);
