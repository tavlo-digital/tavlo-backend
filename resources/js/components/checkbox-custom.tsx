import type {InputHTMLAttributes} from 'react';

type CheckboxCustomProps = Omit<InputHTMLAttributes<HTMLInputElement>, 'type' | 'className'>;

export function CheckboxCustom(props: CheckboxCustomProps) {
    return <input className="checkbox-custom" type="checkbox" {...props} />;
}
