<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ContactController;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/', function () {
    return view('pages.home');
})->name('home');

Route::get('/about', function () {
    return view('pages.about');
})->name('about');

Route::get('/services', function () {
    return view('pages.services');
})->name('services');

Route::get('/contact', function () {
    return view('pages.contact');
})->name('contact');

Route::post('/contact', [ContactController::class, 'submit'])->name('contact.submit');

Route::get('/services/website-development', function () {
    return view('pages.services.website-development');
})->name('services.website');

Route::get('/services/crm-systems', function () {
    return view('pages.services.crm-systems');
})->name('services.crm');

Route::get('/services/business-automation', function () {
    return view('pages.services.business-automation');
})->name('services.automation');

Route::get('/services/technical-support', function () {
    return view('pages.services.technical-support');
})->name('services.support');
