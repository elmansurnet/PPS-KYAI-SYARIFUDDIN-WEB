<?php

test('test_homepage_returns_200', function () {
    $this->get('/')->assertStatus(200);
});
