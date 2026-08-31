<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class RenderDeploymentConfigurationTest extends TestCase
{
    public function test_it_defines_the_complete_render_production_topology(): void
    {
        $blueprintPath = base_path('render.yaml');

        $this->assertFileExists($blueprintPath);

        $blueprint = file_get_contents($blueprintPath);

        foreach ([
            'type: web',
            'type: worker',
            'type: pserv',
            'type: keyvalue',
            'healthCheckPath: /up',
            'kai-rams-predeploy',
            '--queue=rams-imports,default',
            'RAMS_IMPORT_DISK',
            'value: s3',
            'AWS_ACCESS_KEY_ID',
            'RAMS_ADMIN_PASSWORD',
        ] as $expected) {
            $this->assertStringContainsString($expected, $blueprint);
        }
    }

    public function test_it_keeps_production_secrets_out_of_the_committed_blueprint(): void
    {
        $blueprint = file_get_contents(base_path('render.yaml'));

        $this->assertStringNotContainsString('admin1234', $blueprint);
        $this->assertStringNotContainsString('AWS_SECRET_ACCESS_KEY: ', $blueprint);
        $this->assertStringNotContainsString('APP_KEY=', $blueprint);
    }
}
