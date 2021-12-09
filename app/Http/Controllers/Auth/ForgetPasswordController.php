<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Auth\ForgetPasswordService;
use App\Http\Misc\Helpers\Errors;

class ForgetPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password 
    | reset for user's Account 
    | 
    |
    */

    protected $ForgetPasswordService;

    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct(ForgetPasswordService $ForgetPasswordService)
    {
        $this->ForgetPasswordService = $ForgetPasswordService;
    }

    /**
     * @OA\Get(
     * path="/forgot_password",
     * summary="forget password for existing user",
     * description="User can reset password for existing email",
     * operationId="forgetPassword",
     * tags={"Auth"},
     * @OA\Response(
     *    response=200,
     *    description="Successfully redirect",
     * ),
     *   @OA\Response(
     *      response=404,
     *       description="Not Found",
     *   ),
     * )
     */
    /**
     * ForgetPassword Function handle Validate and sending
     * forget password mail to user
     * 
     * @param Request
     * 
     * @return response
     */
    public function ForgetPassword(Request $request)
    {
        $request->validate(
            [
                'email' => 'required',
            ]
        );

        // check if user is exist in DB
        if (!$this->ForgetPasswordService->CheckIfUserExist($request->email))
            return $this->error_response($msg = Errors::NOT_FOUND_USER, $code = 404);

        //create reset password token to user
        $token = $this->ForgetPasswordService->AddToken($request->email);

        if ($token == null)
            return $this->error_response(Errors::GENERATE_TOKEN_ERROR, 400);

        // send ResetPasswordMail
        if (!$this->ForgetPasswordService->SendResetPasswordMail($request->email, $token))
            return $this->error_response(Errors::ERROR_MAIL, 500);

        return $this->success_response('Check your email', 200);
    }
}