"""Entidades de dominio; corresponden a las tablas SQL, sin dependencia de ORM."""
from dataclasses import dataclass
from typing import Literal


@dataclass(frozen=True)
class CategoriaOWASP:
    id: str
    version: str
    nombre: str
    criterio: str
    categoria_cercana: str
    contraste: str
    created_at: str
    updated_at: str


@dataclass(frozen=True)
class Evidencia:
    id: int
    texto: str
    activo: str
    perfil: str
    created_at: str
    updated_at: str
    sistema_id: int | None = None
    archived_at: str | None = None
    analisis_id: int | None = None
    ubicacion: str | None = None
    recomendacion: str | None = None


@dataclass(frozen=True)
class Clasificacion:
    id: int
    evidencia_id: int
    categoria_id: str
    origen: Literal['reglas', 'manual']
    motor_version: str
    explicacion: str
    created_at: str


@dataclass(frozen=True)
class Justificacion:
    id: int
    clasificacion_id: int
    autor: str
    texto: str
    contraste: str
    impacto: str
    dato_faltante: str
    politica_institucional: str
    referencia_asvs: str
    prueba_wstg: str
    created_at: str


@dataclass(frozen=True)
class Revision:
    id: int
    justificacion_id: int
    revisor: str
    decision: Literal['aprobada', 'observada']
    observaciones: str
    created_at: str
    autoevaluacion: bool = False


@dataclass(frozen=True)
class Sistema:
    id: int
    nombre: str
    created_at: str


@dataclass(frozen=True)
class AnalisisZIP:
    id: int
    sistema_id: int
    nombre_archivo: str
    resultado: str
    created_at: str
    content_sha256: str | None = None
