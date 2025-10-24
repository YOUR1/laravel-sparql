#!/bin/bash

# Setup script for Apache Jena Fuseki SPARQL endpoint

set -e

echo "🚀 Starting Apache Jena Fuseki..."
docker-compose up -d fuseki

echo "⏳ Waiting for Fuseki to be ready..."

# Wait for Fuseki to be ready (max 60 seconds)
SECONDS=0
MAX_WAIT=60
until curl -f http://localhost:3030/$/ping 2>/dev/null; do
    if [ $SECONDS -ge $MAX_WAIT ]; then
        echo "❌ Fuseki failed to start within $MAX_WAIT seconds"
        exit 1
    fi
    sleep 2
done

echo "✅ Fuseki is ready!"
echo ""
echo "📊 Fuseki Admin UI: http://localhost:3030"
echo "   Username: admin"
echo "   Password: admin"
echo ""
echo "🔍 SPARQL Endpoint: http://localhost:3030/test/sparql"
echo ""
echo "🧪 You can now run your tests with: composer test"
