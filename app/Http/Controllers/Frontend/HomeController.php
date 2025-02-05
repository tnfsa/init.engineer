<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;

/**
 * Class HomeController.
 */
class HomeController extends Controller
{
    /**
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('frontend.index');
    }

    /**
     * @return \
     */
    public function policies()
    {
        return view('frontend.policies');
    }

    /**
     * @return \Illuminate\View\View
     */
    public function team()
    {
        return view('frontend.team');
    }

    /**
     * @param string $value
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function redirect(string $value)
    {
        switch ($value) {
            case 'discord':
                return redirect('https://discord.gg/pRuFQPC');

            default:
                return redirect()->route('frontend.index');
        }
    }
}
