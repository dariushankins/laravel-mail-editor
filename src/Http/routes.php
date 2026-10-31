<?php

use Illuminate\Support\Facades\Route;
use Qoraiche\MailEclipse\Http\Controllers\TemplatesController;
use Qoraiche\MailEclipse\Http\Controllers\MailablesPreviewController;
use Qoraiche\MailEclipse\Http\Controllers\MailablesController;

Route::get('/', [MailablesController::class, 'toMailablesList']);

Route::group(['prefix' => 'templates'], function () {
    Route::get('/', [TemplatesController::class, 'index'])->name('templateList');
    Route::get('new', [TemplatesController::class, 'select'])->name('selectNewTemplate');
    Route::get('new/{type}/{name}/{skeleton}', [TemplatesController::class, 'new'])->name('newTemplate');
    Route::get('edit/{templatename}', [TemplatesController::class, 'view'])->name('viewTemplate');
    Route::post('new', [TemplatesController::class, 'create'])->name('createNewTemplate');
    Route::post('delete', [TemplatesController::class, 'delete'])->name('deleteTemplate');
    Route::post('update', [TemplatesController::class, 'update'])->name('updateTemplate');
    Route::post('preview', [TemplatesController::class, 'previewTemplateMarkdownView'])->name('previewTemplateMarkdownView');
});

Route::group(['prefix' => 'mailables'], function () {
    Route::get('/', [MailablesController::class, 'index'])->name('mailableList');
    Route::get('view/{name}', [MailablesController::class, 'viewMailable'])->name('viewMailable');
    Route::get('edit/template/{name}', [MailablesController::class, 'editMailable'])->name('editMailable');
    Route::post('parse/template', [MailablesController::class, 'parseTemplate'])->name('parseTemplate');

    Route::get('new', [MailablesController::class, 'createMailable'])->name('createMailable');
    Route::post('new', [MailablesController::class, 'generateMailable'])->name('generateMailable');
    Route::post('delete', [MailablesController::class, 'delete'])->name('deleteMailable');

    Route::group(['prefix' => 'preview'], function () {
        Route::post('template', [MailablesPreviewController::class, 'markdownView'])->name('previewMarkdownView');
        Route::get('template/previewerror', [MailablesPreviewController::class, 'previewError'])->name('templatePreviewError');
        Route::get('{name}', [MailablesPreviewController::class, 'mailable'])->name('previewMailable');
    });

    Route::post('send-test', [MailablesController::class, 'sendTest'])->name('sendTestMail');
});
