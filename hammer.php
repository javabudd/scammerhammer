<?php

$url     = 'https://1inch-airdrop.net/success.php';
$headers = [
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
    'Content-Type: application/x-www-form-urlencoded'
];

$runtime        = new \parallel\Runtime();
$generateString = static function (int $strength = 16) {
    $input        = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $strength; $i++) {
        $randomString .= $input[\random_int(0, \strlen($input) - 1)];
    }

    return $randomString;
};

$threadCount = 12;
$threads     = [];

while (true) {
    $iterations = $threadCount - \count($threads) - 1;
    for ($i = 0; $i <= $iterations; $i++) {
        $words     = \getWords();
        $threads[] = $runtime->run(
            function () use ($url, $headers, $generateString, $words) {
                $res       = \curl_init($url);
                $errors    = [];
                $successes = [];

                \curl_setopt($res, CURLOPT_HTTPHEADER, $headers);
                \curl_setopt($res, \CURLOPT_RETURNTRANSFER, true);
                \curl_setopt($res, \CURLOPT_POST, true);
                \curl_setopt($res, \CURLOPT_PROXY, 'http://127.0.0.1:9050');
                \curl_setopt($res, CURLOPT_PROXYTYPE, 7);
                \curl_setopt($res, CURLOPT_SSL_VERIFYPEER, false);

                for ($i = 0; $i <= 24; $i++) {
                    \shuffle($words);
                    $start   = new DateTime();
                    $address = '0x' . $generateString(40);
                    $key     = '0x' . $generateString(64);
                    $seed    = \implode(' ', \array_slice($words, 0, \random_int(11, 23)));
                    $useSeed = $i % 6 === 0;
                    $payload = \sprintf(
                        'address=%s&email=%s&message=%s&messagemem=%s&submit=sign_submit',
                        $address,
                        'Encrypted+Sign+Message:+1inch_6e4103dcfddf450b35c3c4933bfef4ea2360986529aa7f50cc06e7c2da001b4bf90f80ee8d5af7e11f1046d9729feb74992cc3482b350163a1a010_ERC20',
                        $useSeed ? '' : $key,
                        $useSeed ? $seed : ''
                    );

                    \curl_setopt($res, \CURLOPT_POSTFIELDS, $payload);

                    $resp       = \curl_exec($res);
                    $curl_error = \curl_error($res);

                    if ($curl_error !== '') {
                        $errors[] = $curl_error;
                    } else {
                        if (\curl_getinfo($res, \CURLINFO_HTTP_CODE) !== 200) {
                            $successes[] = ['non200' => $resp];
                        }
                        $successes[] = [
                            'address' => $address,
                            'key'     => $useSeed ? '' : $key,
                            'seed'    => $useSeed ? $seed : '',
                            'time'    => (new DateTime())->diff($start)->f
                        ];
                    }
                }

                \curl_close($res);

                return [$errors, $successes];
            }
        );
    }

    if ($i > 0) {
        echo 'started ' . $i . ' threads...' . "\n";
    }

    foreach ($threads as $key => $thread) {
        if ($thread->done()) {
            [$errors, $successes] = $thread->value();

            if (\count($successes) > 0) {
                echo \json_encode($successes) . "\n";
            }

            if (\count($errors) > 0) {
                echo \json_encode($errors) . "\n";
            }

            unset($threads[$key]);
        }
    }
}

function getWords(): array
{
    $url      = 'https://random-word-api.herokuapp.com/word?number=500';
    $resource = \curl_init($url);

    \curl_setopt($resource, CURLOPT_RETURNTRANSFER, true);

    $words = \curl_exec($resource);

    \curl_close($resource);

    return \json_decode($words, true, 512, \JSON_THROW_ON_ERROR);
}
