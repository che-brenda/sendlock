<?php

test('the landing page renders the animated risk chart', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Top Risk Reasons')
        ->assertSee('Similar Domain')
        ->assertSee('Scans analysed')
        ->assertSee('rc-segments', false);   // the animated donut markup
});
