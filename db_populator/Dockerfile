FROM python:3.9-slim-buster
WORKDIR /app
COPY db_populator/requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt
COPY populate_db.py .
