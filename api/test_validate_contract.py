"""Negative checks ensure contract validation rejects meaningful regressions."""

import copy
import re
import unittest

from jsonschema import Draft202012Validator, SchemaError, ValidationError
from validate_contract import (
    ROOT,
    JSONObject,
    load_json,
    resolve,
    unique_object,
    validate,
)


class ContractValidationTest(unittest.TestCase):
    document: JSONObject

    def setUp(self) -> None:
        self.document = copy.deepcopy(load_json(ROOT / 'openapi.json'))

    def object_at(self, pointer: str) -> JSONObject:
        value = resolve(self.document, pointer)
        if not isinstance(value, dict):
            raise TypeError('Expected an object in the fixture.')
        return value

    def test_current_contract_passes(self) -> None:
        self.assertEqual(30, validate(self.document))

    def test_contract_covers_every_registered_runtime_route(self) -> None:
        source = (ROOT.parent / 'hotel-app/routes/api.php').read_text(encoding='utf-8')
        table_start = source.index("->prefix('table')")
        kiosk_start = source.index("->prefix('kiosk')")
        kitchen_start = source.index("staff_or_device:kitchen.view,kitchen")
        runtime: set[tuple[str, str]] = set()
        route_pattern = re.compile(r"Route::(get|post|patch|delete)\('([^']+)'")
        for match in route_pattern.finditer(source):
            prefix = '/table' if table_start < match.start() < kiosk_start else ''
            if kiosk_start < match.start() < kitchen_start:
                prefix = '/kiosk'
            runtime.add((match.group(1), prefix + match.group(2)))
        contract = {
            (method, path)
            for path, item in self.document['paths'].items()
            for method in item
            if method in {'get', 'post', 'put', 'patch', 'delete', 'head', 'options', 'trace'}
        }
        self.assertEqual(runtime, contract)

    def test_every_operation_declares_authentication_and_mutation_csrf(self) -> None:
        for path, item in self.document['paths'].items():
            for method, operation in item.items():
                if method not in {'get', 'post', 'put', 'patch', 'delete', 'head', 'options', 'trace'}:
                    continue
                self.assertIn('security', operation, f'{method.upper()} {path}')
                if method not in {'get', 'head', 'options'}:
                    refs = {parameter.get('$ref') for parameter in operation.get('parameters', [])}
                    self.assertIn('#/components/parameters/Csrf', refs, f'{method.upper()} {path}')

    def test_resource_version_tags_match_runtime_integer_boundaries(self) -> None:
        validator = Draft202012Validator(self.object_at('#/components/schemas/ResourceVersionTag'))
        for value in ['"v1"', '"v2"', '"v9223372036854775807"']:
            validator.validate(value)
        for value in ['*', 'W/"v1"', '"v0"', '"v01"', '"v9223372036854775808"', '"v1", "v2"', '"v1"\n']:
            with self.assertRaises(ValidationError):
                validator.validate(value)

    def test_missing_operation_responses_fails(self) -> None:
        del self.object_at('#/paths/~1health~1live/get')['responses']
        with self.assertRaises(ValueError):
            validate(self.document)

    def test_missing_info_fails(self) -> None:
        del self.document['info']
        with self.assertRaises(ValidationError):
            validate(self.document)

    def test_health_security_scope(self) -> None:
        self.assertEqual([], self.object_at('#/paths/~1health~1live/get')['security'])
        self.assertEqual([{'staffSession': []}], self.object_at('#/paths/~1health~1ready/get')['security'])

    def test_unresolved_reference_fails(self) -> None:
        self.object_at('#/components/schemas/FieldError/properties/code')['$ref'] = '#/components/schemas/Missing'
        with self.assertRaises(KeyError):
            validate(self.document)

    def test_invalid_or_leaky_example_fails(self) -> None:
        self.object_at('#/components/responses/InternalError/content/application~1json/example/error')['sql'] = 'private database detail'
        with self.assertRaises(ValidationError):
            validate(self.document)

    def test_missing_request_id_fails(self) -> None:
        del self.object_at('#/paths/~1health~1live/get/responses/200/content/application~1json/example')['requestId']
        with self.assertRaises(ValidationError):
            validate(self.document)

    def test_invalid_inline_schema_fails(self) -> None:
        self.object_at('#/paths/~1health~1live/get/responses/200/content/application~1json')['schema'] = {'type': 'object', 'maxLength': 'not-an-integer'}
        with self.assertRaises(SchemaError):
            validate(self.document)

    def test_invalid_header_schema_fails(self) -> None:
        self.object_at('#/components/responses/RateLimited/headers/Retry-After/schema')['maxLength'] = 'invalid'
        with self.assertRaises(SchemaError):
            validate(self.document)

    def test_unknown_global_security_fails(self) -> None:
        self.document['security'] = [{'nonexistent': []}]
        with self.assertRaises(ValueError):
            validate(self.document)

    def test_string_patterns_reject_trailing_newlines(self) -> None:
        for pointer, valid in [('#/components/schemas/RequestId', 'req_01'),
                               ('#/components/schemas/ErrorCode', 'INTERNAL_ERROR'),
                               ('#/components/responses/RateLimited/headers/Retry-After/schema', '30')]:
            validator = Draft202012Validator(self.object_at(pointer))
            self.assertTrue(validator.is_valid(valid))
            for suffix in ['\n', '\r', '\r\n']:
                self.assertFalse(validator.is_valid(valid + suffix))

    def test_duplicate_keys_fail(self) -> None:
        with self.assertRaises(ValueError):
            unique_object([('data', {}), ('data', {})])


if __name__ == '__main__':
    unittest.main()
