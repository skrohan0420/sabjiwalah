<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Otp extends BaseConfig
{
    // Opt in only for a local development installation. Production never returns test codes.
    public bool $developmentMode = false;
}
