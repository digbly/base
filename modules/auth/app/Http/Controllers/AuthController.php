<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\AccessToken;
use Laravel\Passport\RefreshToken;
use Modules\Admin\Http\Resources\MessageResource;
use Modules\Admin\Http\Resources\UserResource;
use Modules\Auth\Http\Requests\ChangePasswordRequest;
use Modules\Auth\Http\Requests\ForgotPasswordRequest;
use Modules\Auth\Http\Requests\RegisterRequest;
use Modules\Auth\Http\Requests\ResendVerificationEmailRequest;
use Modules\Auth\Http\Requests\ResetPasswordRequest;
use Modules\Auth\Http\Requests\UpdateProfileRequest;
use Modules\Auth\Models\User;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    #[OA\Post(
        path: '/api/v1/auth/user/register',
        summary: 'Register New User',
        operationId: 'user.register',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        type: RegisterRequest::class
                    )
                ),
            ]
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Registration Success',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: UserResource::class),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validation Error'),
        ]
    )]
    public function register(RegisterRequest $request): UserResource
    {
        $registerData = $request->safe()->all();

        /** @var User $user */
        $user = DB::transaction(function () use ($registerData) {
            $user = new User;
            $user->fill($registerData);
            $user->save();

            return $user;
        });

        event(new Registered($user));

        return UserResource::make($user);
    }

    #[OA\Get(
        path: '/api/v1/auth/user/profile',
        summary: 'Get Authenticated User Profile',
        operationId: 'user.profile',
        tags: ['Auth'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Authenticated user',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: UserResource::class),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function profile(Request $request): UserResource
    {
        return UserResource::make($request->user('api'));
    }

    #[OA\Post(
        path: '/api/v1/auth/user/profile',
        summary: 'Update Authenticated User Profile',
        operationId: 'user.update-profile',
        tags: ['Auth'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'multipart/form-data',
                    schema: new OA\Schema(type: UpdateProfileRequest::class)
                ),
            ]
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Profile updated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: UserResource::class),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation Error'),
        ]
    )]
    public function updateProfile(UpdateProfileRequest $request): UserResource
    {
        /** @var User $user */
        $user = $request->user('api');

        $user->fill($request->safe()->only('name'));
        $user->save();

        if ($request->hasFile('avatar')) {
            $user->addMediaFromRequest('avatar')->toMediaCollection('avatar');
        }

        return UserResource::make($user->refresh());
    }

    #[OA\Post(
        path: '/api/v1/auth/user/resend-verification-email',
        summary: 'Resend Verification Email',
        operationId: 'user.resend-verification-email',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        type: ResendVerificationEmailRequest::class
                    )
                ),
            ]
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Verification Link Sent',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: MessageResource::class),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validation Error'),
        ]
    )]
    public function resendVerificationEmail(ResendVerificationEmailRequest $request): MessageResource
    {
        $user = User::query()->where('email', $request->post('email'))->first();

        if ($user === null || $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => ['Your email has already been verified or account does not exist.'],
            ]);
        }

        $user->sendEmailVerificationNotification();

        return MessageResource::make('Verification link sent!');
    }

    #[OA\Post(
        path: '/api/v1/auth/user/email/verify/{id}/{hash}',
        summary: 'Verify Email',
        operationId: 'user.verify-email',
        tags: ['Auth'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'hash', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Email Verified',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: MessageResource::class),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Invalid Token'),
        ]
    )]
    public function verifyEmail(Request $request, string $id, string $hash): MessageResource
    {
        $user = User::query()->find($id);

        if ($user === null || !hash_equals((string) $user->getKey(), $id) || !hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            throw ValidationException::withMessages([
                'token' => ['Invalid verification token.'],
            ]);
        }

        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return MessageResource::make('Your email has been verified.');
    }

    #[OA\Post(
        path: '/api/v1/auth/user/forgot-password',
        summary: 'Forgot Password',
        operationId: 'user.forgot-password',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        type: ForgotPasswordRequest::class
                    )
                ),
            ]
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Password reset link emailed',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: MessageResource::class),
                    ]
                )
            ),
        ]
    )]
    public function forgotPassword(ForgotPasswordRequest $request): MessageResource
    {
        $user = User::query()->where('email', $request->post('email'))->first();

        if ($user !== null) {
            $token = Password::createToken($user);
            $user->sendPasswordResetNotification($token);
        }

        return MessageResource::make('We have e-mailed your password reset link!');
    }

    #[OA\Post(
        path: '/api/v1/auth/user/reset-password',
        summary: 'Reset Password',
        operationId: 'user.reset-password',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        type: ResetPasswordRequest::class
                    )
                ),
            ]
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Password Reset Success',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: MessageResource::class),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validation Error'),
        ]
    )]
    public function resetPassword(ResetPasswordRequest $request): MessageResource
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password]);
                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return MessageResource::make('Your password has been reset!');
    }

    #[OA\Put(
        path: '/api/v1/auth/user/change-password',
        summary: 'Change Password',
        operationId: 'user.change-password',
        tags: ['Auth'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        type: ChangePasswordRequest::class
                    )
                ),
            ]
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Password Changed',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: MessageResource::class),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validation Error'),
        ]
    )]
    public function changePassword(ChangePasswordRequest $request): MessageResource
    {
        /** @var User $user */
        $user = $request->user('api');

        if (!Hash::check($request->post('current_password'), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password does not match.'],
            ]);
        }

        $user->forceFill(['password' => $request->post('password')]);
        $user->save();

        return MessageResource::make('Password changed successfully!');
    }

    #[OA\Post(
        path: '/api/v1/auth/user/logout',
        summary: 'Logout',
        operationId: 'user.logout',
        tags: ['Auth'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Logout Success',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: MessageResource::class),
                    ]
                )
            ),
        ]
    )]
    public function logout(Request $request): MessageResource
    {
        $token = $request->user('api')?->token();

        if ($token instanceof AccessToken) {
            $accessTokenId = $token->oauth_access_token_id ?? null;

            if ($accessTokenId !== null) {
                RefreshToken::query()
                    ->where('access_token_id', $accessTokenId)
                    ->update(['revoked' => true]);
            }

            $token->revoke();
        }

        return MessageResource::make('Successfully logged out');
    }
}
