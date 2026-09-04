<?php
declare(strict_types=1);

namespace Tests\Unit\Http;

use Oshim\Testing\TestCase;
use Oshim\Http\Request;
use Oshim\Http\Response;
use Oshim\Http\Exceptions\ValidationException;

class RequestResponseTest extends TestCase
{
    public function testRequestCreationAndInspection(): void
    {
        $request = Request::create(
            method: 'POST',
            uri: '/api/v1/servers?filter=active',
            parameters: ['name' => 'web-01', 'plan_id' => 5],
            cookies: ['theme' => 'dark'],
            files: [],
            server: ['HTTP_AUTHORIZATION' => 'Bearer sample_token_123', 'HTTP_ACCEPT' => 'application/json']
        );

        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('/api/v1/servers', $request->getPath());
        $this->assertEquals('active', $request->query('filter'));
        $this->assertEquals('web-01', $request->post('name'));
        $this->assertEquals(5, $request->input('plan_id'));
        $this->assertEquals('sample_token_123', $request->bearerToken());
        $this->assertEquals('dark', $request->cookie('theme'));
        $this->assertTrue($request->wantsJson());
    }

    public function testRequestDotNotationInput(): void
    {
        $jsonPayload = json_encode([
            'user' => [
                'profile' => [
                    'city' => 'Dhaka',
                    'country' => 'Bangladesh',
                ],
            ],
        ]);

        $request = Request::create(
            method: 'POST',
            uri: '/api/user',
            server: ['HTTP_CONTENT_TYPE' => 'application/json'],
            content: $jsonPayload
        );

        $this->assertEquals('Dhaka', $request->input('user.profile.city'));
        $this->assertEquals('Bangladesh', $request->input('user.profile.country'));
        $this->assertNull($request->input('user.profile.zip'));
    }

    public function testRequestValidationSuccess(): void
    {
        $request = Request::create(
            method: 'POST',
            uri: '/register',
            parameters: [
                'email'                 => 'admin@oshim.cloud',
                'password'              => 'SuperSecret123!',
                'password_confirmation' => 'SuperSecret123!',
                'role'                  => 'client',
                'age'                   => 25,
                'uuid'                  => '550e8400-e29b-41d4-a716-446655440000',
            ]
        );

        $validated = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|min:8|confirmed',
            'role'     => 'required|in:admin,reseller,client',
            'age'      => 'required|numeric|min:18',
            'uuid'     => 'required|uuid',
        ]);

        $this->assertEquals('admin@oshim.cloud', $validated['email']);
        $this->assertEquals('client', $validated['role']);
    }

    public function testRequestValidationFailureThrowsException(): void
    {
        $request = Request::create(
            method: 'POST',
            uri: '/register',
            parameters: [
                'email'    => 'invalid-email-string',
                'password' => 'short',
            ]
        );

        $this->assertThrows(function () use ($request) {
            $request->validate([
                'email'    => 'required|email',
                'password' => 'required|min:8',
                'role'     => 'required',
            ]);
        }, ValidationException::class);
    }

    public function testResponseJsonEncoding(): void
    {
        $data = ['success' => true, 'id' => 101, 'name' => 'VPS Node'];
        $response = Response::json($data, 201);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertTrue($response->isSuccessful());
        $this->assertEquals('application/json; charset=UTF-8', $response->getHeaders()->get('content-type'));
        $this->assertStringContainsString('"success":true', $response->getContent());
        $this->assertStringContainsString('"name":"VPS Node"', $response->getContent());
    }

    public function testResponseRedirectAndHeaders(): void
    {
        $response = Response::redirect('/client/dashboard');

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertTrue($response->isRedirect());
        $this->assertEquals('/client/dashboard', $response->getHeaders()->get('location'));
    }
}
