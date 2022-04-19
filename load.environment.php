<?php

use Symfony\Component\Dotenv\Dotenv;

if (file_exists(__DIR__ . '/.env')) {
  $dotenv = new Dotenv();
  $dotenv->load(__DIR__ . '/.env');
}
