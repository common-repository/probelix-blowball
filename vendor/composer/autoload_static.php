<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInite815de2053885912e9fee97ad041f28f
{
    public static $prefixLengthsPsr4 = array (
        'P' => 
        array (
            'PbxVendor\\' => 10,
            'PbxBlowball\\' => 12,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'PbxVendor\\' => 
        array (
            0 => __DIR__ . '/../..' . '/vendor_prefixed',
        ),
        'PbxBlowball\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInite815de2053885912e9fee97ad041f28f::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInite815de2053885912e9fee97ad041f28f::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}