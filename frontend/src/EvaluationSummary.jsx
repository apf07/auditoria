import React from 'react';
import {downloadCSV,evaluationRows} from './report';

export default function EvaluationSummary({scan,rows,catalog,systemName}){
 const entries=evaluationRows(scan,rows,catalog);
 return <section className="panel evaluation-summary" id="evaluation-result"><div className="panel-title"><div><div className="eyebrow">MARCO ACADÉMICO · CUADRO DEL PRODUCTO ESPERADO</div><h2>Evaluación aplicada a {systemName}</h2><p>{scan.resultado.origin==='url'?'URL':'ZIP'}: {scan.nombre_archivo} · Análisis #{scan.id}. Este cuadro muestra lo generado y el trabajo humano pendiente.</p></div><button className="secondary" onClick={()=>downloadCSV(`cuadro-evaluacion-${scan.id}.csv`,['Elemento','Especificación','Resultado de esta evaluación','Estado'],entries)}>↓ Exportar cuadro</button></div><div className="table-scroll"><table><thead><tr><th>ELEMENTO</th><th>ESPECIFICACIÓN</th><th>RESULTADO DE ESTA EVALUACIÓN</th><th>ESTADO</th></tr></thead><tbody>{entries.map(([label,spec,result,status])=><tr key={label}><th scope="row">{label}</th><td>{spec}</td><td>{result}</td><td><span className="badge">{status}</span></td></tr>)}</tbody></table></div></section>;
}
