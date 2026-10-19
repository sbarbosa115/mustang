import {describe, expect, it} from 'vitest';
import {renderTemplate, unknownPlaceholders} from './render';

describe('renderTemplate', () => {
  it('fills every known placeholder', () => {
    expect(
      renderTemplate('Hi {first_name}, this is {rep_name} at {dealership}.', {
        first_name: 'Sarah',
        rep_name: 'Sam',
        dealership: 'Mustang Motors',
      }),
    ).toBe('Hi Sarah, this is Sam at Mustang Motors.');
  });

  it('leaves a placeholder without a value visible so the rep fills it in', () => {
    expect(renderTemplate('See you at {appointment_time}', {})).toBe(
      'See you at {appointment_time}',
    );
  });

  it('lists placeholders that are not supported', () => {
    expect(
      unknownPlaceholders('Hi {first_name} {nickname} {nickname}'),
    ).toEqual(['nickname']);
  });
});
