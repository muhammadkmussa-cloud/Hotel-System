<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\ApplicationRequest;
use App\Http\Middleware\ParseJsonInput;
use App\Http\Requests\InvalidJsonInput;
use App\Http\Requests\JsonInput;
use Illuminate\Http\Request;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response;

final class JsonInputTest extends TestCase
{
    private function parse(string $body, string $type = 'application/json'): Request
    {
        $request = ApplicationRequest::createFromBase(SymfonyRequest::create('/api/v1/probe', 'POST', server: ['CONTENT_TYPE' => $type], content: $body));
        (new ParseJsonInput)->handle($request, fn () => new Response());
        return $request;
    }

    public function testParserPreservesStringsEmptyValuesAndLargeIntegers(): void
    {
        $request = $this->parse('{"name":"  demo  ","empty":"","large":9223372036854775808,"nested":{"a":1},"other":{"a":2}}');
        self::assertSame('  demo  ', $request->json('name'));
        self::assertSame('', $request->json('empty'));
        self::assertSame('9223372036854775808', $request->json('large'));
    }

    public function testCaptureDefersDecodingAndValidatedNumbersRetainTheirTypes(): void
    {
        $captured = ApplicationRequest::createFromBase(SymfonyRequest::create('/api/v1/probe', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: '{"value":1}'));
        self::assertSame([], $captured->request->all());
        $input = new JsonInput(new Factory(new Translator(new ArrayLoader, 'en')));
        $result = $input->validate($this->parse('{"value":1.0}'), ['value' => ['required', 'numeric']]);
        self::assertSame(1.0, $result['value']);
    }

    public function testMalformedAndAmbiguousObjectsAreRejected(): void
    {
        foreach (['{', '[]', 'null', 'true', '"text"', '{"a":1,}', '{"a":NaN}', '{"a":1e999}', "{\"a\":\"\xff\"}", '{"a":1,"a":2}', '{"a":1,"\\u0061":2}', '{"x":[{"a":1,"a":2}]}', str_repeat('{"a":', 33).'0'.str_repeat('}',33)] as $body) {
            try {
                $this->parse($body);
                self::fail('Malformed input was accepted.');
            } catch (InvalidJsonInput $error) {
                self::assertSame(400, $error->getResponse()->getStatusCode());
                self::assertStringNotContainsString('secret-marker', $error->getResponse()->getContent());
            }
        }
    }

    public function testSizeBoundaryUsesActualBytesWithoutContentLength(): void
    {
        self::assertNotNull($this->parse('{"a":"'.str_repeat('x', ParseJsonInput::MAX_BYTES - 8).'"}'));
        try {
            $this->parse('{"a":"'.str_repeat('x', ParseJsonInput::MAX_BYTES - 7).'"}');
            self::fail('Oversized input accepted.');
        } catch (InvalidJsonInput $error) {
            self::assertSame(413, $error->getResponse()->getStatusCode());
        }
    }

    public function testLongStringsDoNotDisableDuplicateDetection(): void
    {
        foreach ([str_repeat('x', 60000), str_repeat('\\\\', 30000), str_repeat('\\"', 30000)] as $encoded) {
            self::assertNotNull($this->parse('{"long":"'.$encoded.'","a":1}'));
            try {
                $this->parse('{"long":"'.$encoded.'","a":1,"a":2}');
                self::fail('Long input bypassed duplicate detection.');
            } catch (InvalidJsonInput $error) {
                self::assertSame(400, $error->getResponse()->getStatusCode());
            }
        }
    }

    public function testStrictBodyValidationRejectsUndeclaredMembersAtEveryLevel(): void
    {
        $input = new JsonInput(new Factory(new Translator(new ArrayLoader, 'en')));
        $rules = ['items' => ['required', 'array', 'list', 'max:5'], 'items.*.mealId' => ['required', 'string', 'max:36'], 'note' => ['sometimes', 'string', 'max:20']];
        $valid = $this->parse('{"items":[{"mealId":"demo"}],"note":"  hi  "}');
        $valid->query->set('guestId', 'attacker');
        self::assertSame(['note' => '  hi  ', 'items' => [['mealId' => 'demo']]], $input->validate($valid, $rules));
        foreach (['{"items":[{"mealId":"demo"}],"guestId":"secret-marker"}', '{"items":[{"mealId":"demo","priceMinor":1}]}', '{"items":[{"mealId":"demo","paid":true}]}', '{"items":[{"mealId":{}}]}', '{"items":[] ,"note":'.json_encode(str_repeat('x',21)).'}', '{}', '{"items":[{"mealId":"demo","x.y":1}]}'] as $body) {
            try {
                $input->validate($this->parse($body), $rules);
                self::fail('Invalid fields accepted.');
            } catch (InvalidJsonInput $error) {
                self::assertSame(422, $error->getResponse()->getStatusCode());
                self::assertStringNotContainsString('secret-marker', $error->getResponse()->getContent());
            }
        }
    }

    public function testWildcardMatchesOnlyListIndexesNotObjectMemberNames(): void
    {
        $input = new JsonInput(new Factory(new Translator(new ArrayLoader, 'en')));
        $this->expectException(InvalidJsonInput::class);
        $input->validate($this->parse('{"items":{"*":{"mealId":"demo"}}}'), [
            'items' => ['array'], 'items.*' => ['array'], 'items.*.mealId' => ['string'],
        ]);
    }

    public function testUnsupportedMediaAndMethodOverridesFailClosed(): void
    {
        foreach (['text/plain', 'application/x-www-form-urlencoded', 'application/json; charset=latin1', 'application/jsonp', 'application/vendor+json'] as $type) {
            try { $this->parse('{}', $type); self::fail('Media type accepted.'); }
            catch (InvalidJsonInput $error) { self::assertSame(415, $error->getResponse()->getStatusCode()); }
        }
        foreach (['/api/v1/probe?_method=DELETE', '/api/v1/probe?_method[]=DELETE'] as $url) {
            $request = ApplicationRequest::createFromBase(SymfonyRequest::create($url, 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: '{}'));
            try { (new ParseJsonInput)->handle($request, fn () => new Response); self::fail('Query method override accepted.'); }
            catch (InvalidJsonInput $error) { self::assertSame(400, $error->getResponse()->getStatusCode()); }
        }
        $request = Request::create('/api/v1/probe', 'POST', server: ['HTTP_X_HTTP_METHOD_OVERRIDE' => 'DELETE'], content: '{}');
        try { (new ParseJsonInput)->handle($request, fn () => new Response); self::fail('Method override accepted.'); }
        catch (InvalidJsonInput $error) { self::assertSame(400, $error->getResponse()->getStatusCode()); }
    }
}
