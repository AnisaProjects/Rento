"""
Database settings for Rento's Python scripts.

Credentials are never passed on the command line (they would be visible in the process
list). They come from, in this order:

1. Environment variables RENTO_DB_HOST, RENTO_DB_PORT, RENTO_DB_USER, RENTO_DB_PASS,
   RENTO_DB_NAME. PHP sets these from includes/config.php when it starts a script
   (tools/run-forecast.php, admin "Recompute prices").
2. An INI file named by RENTO_DB_CONFIG, with a [database] section:
       [database]
       host = 127.0.0.1
       port = 8889
       user = rento
       password = ...
       name = rento
"""

from __future__ import annotations

import configparser
import os

import pymysql


class DatabaseConfigError(RuntimeError):
    pass


def db_settings() -> dict:
    env = os.environ
    if env.get("RENTO_DB_NAME") and env.get("RENTO_DB_USER"):
        return {
            "host": env.get("RENTO_DB_HOST", "127.0.0.1"),
            "port": int(env.get("RENTO_DB_PORT", "3306")),
            "user": env["RENTO_DB_USER"],
            "password": env.get("RENTO_DB_PASS", ""),
            "database": env["RENTO_DB_NAME"],
        }
    path = env.get("RENTO_DB_CONFIG")
    if path:
        parser = configparser.ConfigParser()
        if not parser.read(path) or "database" not in parser:
            raise DatabaseConfigError(f"No [database] section in {path}")
        section = parser["database"]
        return {
            "host": section.get("host", "127.0.0.1"),
            "port": section.getint("port", 3306),
            "user": section["user"],
            "password": section.get("password", ""),
            "database": section["name"],
        }
    raise DatabaseConfigError("Set RENTO_DB_* environment variables or RENTO_DB_CONFIG.")


def connect() -> pymysql.connections.Connection:
    """A PyMySQL connection returning rows as dicts, in UTC-neutral local dates."""
    settings = db_settings()
    # localhost would make PyMySQL look for a socket; MAMP listens on TCP 127.0.0.1.
    if settings["host"] == "localhost":
        settings["host"] = "127.0.0.1"
    return pymysql.connect(
        **settings,
        charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor,
        autocommit=False,
        connect_timeout=10,
    )


def sqlalchemy_engine():
    """SQLAlchemy engine for the gradient-boosting scripts (pandas.read_sql)."""
    from sqlalchemy import create_engine
    from sqlalchemy.engine import URL

    settings = db_settings()
    url = URL.create(
        "mysql+pymysql",
        username=settings["user"],
        password=settings["password"],
        host="127.0.0.1" if settings["host"] == "localhost" else settings["host"],
        port=settings["port"],
        database=settings["database"],
    )
    return create_engine(url)
