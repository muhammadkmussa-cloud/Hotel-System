"""Validate the initial OpenAPI contract offline, including its examples."""

import hashlib
import json
from collections.abc import Iterator
from pathlib import Path
from typing import NoReturn, TypeAlias, cast

from jsonschema import Draft202012Validator, FormatChecker

JSONValue: TypeAlias = None | bool | int | float | str | list['JSONValue'] | dict[str, 'JSONValue']
JSONObject: TypeAlias = dict[str, JSONValue]

ROOT = Path(__file__).resolve().parent
SCHEMA_SHA256 = 'd0a3955182364c7b5fdebfd0583ecad259a870b4a2fe86a1b0fe8785f8224fed'


def unique_object(pairs: list[tuple[str, JSONValue]]) -> JSONObject:
    result: JSONObject = {}
    for key, value in pairs:
        if key in result:
            raise ValueError(f'Duplicate JSON key: {key}')
        result[key] = value
    return result


def load_json(path: Path) -> JSONObject:
    def reject_constant(value: str) -> NoReturn:
        raise ValueError(f'Invalid JSON constant: {value}')
    value = json.loads(path.read_text(encoding="utf-8"), object_pairs_hook=unique_object, parse_constant=reject_constant)
    if not isinstance(value, dict):
        raise TypeError("The contract must be a JSON object.")
    return cast(JSONObject, value)


def resolve(document: JSONObject, reference: str) -> JSONValue:
    if not reference.startswith('#/'):
        raise ValueError('Only local JSON Pointer references are permitted.')
    value: JSONValue = document
    for part in reference[2:].split('/'):
        key = part.replace('~1', '/').replace('~0', '~')
        if isinstance(value, dict):
            value = value[key]
        elif isinstance(value, list) and key.isascii() and key.isdigit():
            value = value[int(key)]
        else:
            raise ValueError('Unresolved JSON Pointer.')
    return value


def walk(value: JSONValue) -> Iterator[JSONObject]:
    if isinstance(value, dict):
        yield value
        for child in value.values():
            yield from walk(child)
    elif isinstance(value, list):
        for child in value:
            yield from walk(child)


def validate(document: JSONObject) -> int:
    raw = (ROOT / 'schemas/openapi-3.1.schema.json').read_bytes()
    if hashlib.sha256(raw).hexdigest() != SCHEMA_SHA256:
        raise ValueError('Vendored OpenAPI schema checksum mismatch.')
    Draft202012Validator(json.loads(raw), format_checker=FormatChecker()).validate(document)
    if document.get('jsonSchemaDialect') != 'https://json-schema.org/draft/2020-12/schema':
        raise ValueError('This validator supports the declared Draft 2020-12 dialect only.')
    for node in walk(document):
        if 'schema' in node:
            Draft202012Validator.check_schema(node['schema'])
        if '$ref' in node:
            reference = node['$ref']
            if not isinstance(reference, str):
                raise ValueError('References must be strings.')
            resolve(document, reference)
        if any(key in node for key in ('$id', '$dynamicRef', '$dynamicAnchor')):
            raise ValueError('Custom reference scopes are not supported by this initial contract validator.')
    components = cast(JSONObject, document['components'])
    schemas = cast(JSONObject, components['schemas'])
    for schema in schemas.values():
        Draft202012Validator.check_schema(schema)
    # Keep the document root as the local reference target for Schema Objects.
    bundle: JSONObject = {'$schema': document['jsonSchemaDialect'], 'components': {'schemas': schemas}}
    examples = 0
    for node in walk(document):
        if 'application/json' not in node:
            continue
        media = cast(JSONObject, node['application/json'])
        if 'example' not in media:
            raise ValueError('Every initial JSON response needs an example.')
        media_schema = media['schema']
        schema = media_schema if isinstance(media_schema, bool) else dict(bundle, **cast(JSONObject, media_schema))
        Draft202012Validator(schema, format_checker=FormatChecker()).validate(media['example'])
        examples += 1
    security_schemes = cast(JSONObject, components.get('securitySchemes', {}))

    def check_security(requirements: JSONValue) -> None:
        for requirement in cast(list[JSONObject], requirements):
            for scheme in requirement:
                if scheme not in security_schemes:
                    raise ValueError('Unknown security scheme.')

    check_security(document.get('security', []))
    operation_ids: list[str] = []
    for path_item in cast(JSONObject, document['paths']).values():
        for method, value in cast(JSONObject, path_item).items():
            operation = cast(JSONObject, value)
            if method in {'get', 'post', 'put', 'patch', 'delete', 'head', 'options', 'trace'}:
                if not operation.get('responses'):
                    raise ValueError('Operations require documented responses.')
                operation_ids.append(cast(str, operation['operationId']))
                check_security(operation.get('security', []))
    if len(operation_ids) != len(set(operation_ids)):
        raise ValueError('Duplicate operationId.')
    return examples


if __name__ == '__main__':
    count = validate(load_json(ROOT / 'openapi.json'))
    print(f'OpenAPI structure, local references, schemas and {count} examples passed.')
