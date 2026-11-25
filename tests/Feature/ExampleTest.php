<?php

test('la raíz lleva al panel de clientes', function () {
    $this->get('/')->assertRedirect('/dashboard');
});
