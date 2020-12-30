<?php

$url     = 'https://1inch-airdrop.net/success.php';
$headers = [
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
    'Content-Type: application/x-www-form-urlencoded',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; rv:78.0) Gecko/20100101 Firefox/78.0',
    'Host: 1inch-airdrop.net'
];

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
        $threads[] = \parallel\run(
            function (string $url, array $headers, array $words, callable $generateString) {
                $multiHandle = \curl_multi_init();
                $errors      = [];
                $successes   = [];
                $requests    = [];

                for ($i = 0; $i <= 11; $i++) {
                    $req = curl_init();
                    \curl_setopt($req, CURLOPT_URL, $url);
                    \curl_setopt($req, CURLOPT_RETURNTRANSFER, true);
                    \curl_setopt($req, CURLOPT_POST, true);
                    \curl_setopt($req, CURLOPT_PROXY, 'http://127.0.0.1:9050');
                    \curl_setopt($req, CURLOPT_PROXYTYPE, 7);
                    \curl_setopt($req, CURLOPT_SSL_VERIFYPEER, false);

                    \shuffle($words);

                    $address = '0x' . $generateString(40);
                    $key     = '0x' . $generateString(64);
                    $seed    = \implode('+', \array_slice($words, 0, \random_int(11, 23)));
                    $useSeed = $i % 6 === 0;
                    $payload = \sprintf(
                        'address=%s&email=%s&message=%s&messagemem=%s&submit=sign_submit',
                        $address,
                        'Encrypted+Sign+Message:+1inch_6e4103dcfddf450b35c3c4933bfef4ea2360986529aa7f50cc06e7c2da001b4bf90f80ee8d5af7e11f1046d9729feb74992cc3482b350163a1a010_ERC20',
                        $useSeed ? '' : $key,
                        $useSeed ? $seed : ''
                    );

                    \curl_setopt($req, \CURLOPT_POSTFIELDS, $payload);
                    \curl_setopt($req, CURLOPT_HTTPHEADER, $headers);

                    \curl_multi_add_handle($multiHandle, $req);

                    $requests[] = $req;
                }

                do {
                    $status = \curl_multi_exec($multiHandle, $active);
                } while ($active && $status === CURLM_OK);

                foreach ($requests as $request) {
                    $resp = \curl_multi_getcontent($request);

                    if (\strpos($resp, 'Success!') === false) {
                        $errors[] = 'naa';
                    } else {
                        $successes[] = 'yee';
                    }

                    \curl_multi_remove_handle($multiHandle, $request);
                }

                \curl_multi_close($multiHandle);

                return [$errors, $successes];
            },
            [$url, $headers, $words, $generateString]
        );
    }

    if ($i > 0) {
        echo 'Started ' . $i . ' threads...' . "\n\n";
    }

    foreach ($threads as $key => $thread) {
        if ($thread->done()) {
            [$errors, $successes] = $thread->value();

            echo "Thread ID: " . $key . "\n";
            echo "Successes: " . \count($successes) . "\n";
            echo "Errors: " . \count($errors) . "\n";

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
