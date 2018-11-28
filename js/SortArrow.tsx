import * as React from 'react';

export interface SortArrowProps {
    name: string;
    value: string;
    direction: number;
}

export function SortArrow({name, value, direction}: SortArrowProps) {
    if (name === value) {
        return (<span className='sort_arrow'>
            {direction < 0 ? '▼' : '▲'}
        </span>);
    } else {
        return <span/>;
    }
}